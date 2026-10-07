<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Recordings and photos on bucket disks, as on Laravel Cloud (config/pesu.php). */
class BucketStorageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // What Laravel Cloud does for attached buckets: s3 disks under the names chosen in the dashboard.
        foreach (['aac-recordings', 'aac-photos'] as $name) {
            config(["filesystems.disks.$name" => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'auto', 'bucket' => $name]]);
        }
        config(['pesu.audio_disk' => 'aac-recordings', 'pesu.photo_disk' => 'aac-photos']);

        Storage::fake('aac-recordings');
        Storage::fake('aac-photos');
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        // Copies the audio controller downloaded from the (fake) bucket.
        File::deleteDirectory(storage_path('framework/cache/aac-audio'));
        parent::tearDown();
    }

    private function voice(): UploadedFile
    {
        return UploadedFile::fake()->create('voice.webm', 20, 'audio/webm');
    }

    public function test_recordings_are_stored_on_the_recordings_bucket_not_the_local_disk(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = Tile::whereNull('user_id')->first();

        $this->actingAs($therapist)->post("/aac/tiles/{$tile->id}/audio", ['ta_audio' => $this->voice()], ['Accept' => 'application/json'])->assertOk();

        $path = $tile->fresh()->ta_audio_path;
        Storage::disk('aac-recordings')->assertExists($path);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_playback_checks_permission_then_serves_the_bucket_file(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = Tile::whereNull('user_id')->first();
        $this->actingAs($therapist)->post("/aac/tiles/{$tile->id}/audio", ['ta_audio' => $this->voice()], ['Accept' => 'application/json'])->assertOk();
        $path = $tile->fresh()->ta_audio_path;

        // Fake uploads report a size but hold no bytes, so give the bucket file real content (1 KB).
        $bytes = implode('', array_map(fn ($i) => chr($i % 256), range(0, 1023)));
        Storage::disk('aac-recordings')->put($path, $bytes);

        $response = $this->actingAs(User::factory()->create())->get("/aac/tiles/{$tile->id}/audio");
        $response->assertOk()->assertHeader('Content-Type', 'audio/webm');
        $this->assertSame($bytes, file_get_contents($response->baseResponse->getFile()->getPathname()));

        // Byte ranges still work (Safari needs them): exactly bytes 0–9 of the real content.
        $range = $this->actingAs(User::factory()->create())->get("/aac/tiles/{$tile->id}/audio", ['Range' => 'bytes=0-9']);
        $range->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-9/1024');
        ob_start();
        $range->baseResponse->sendContent();
        $this->assertSame(substr($bytes, 0, 10), ob_get_clean());

        // Still refused before any link is made.
        $this->actingAs(User::factory()->pending()->create())->get("/aac/tiles/{$tile->id}/audio")->assertRedirect('/account/status');
    }

    public function test_the_recording_cache_folder_can_be_moved_for_read_only_hosts(): void
    {
        // On Vercel only /tmp is writable: AAC_AUDIO_CACHE_PATH=/tmp/aac-audio.
        $cache = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pesu-audio-cache-test-'.uniqid();
        config(['pesu.audio_cache_path' => $cache]);

        $therapist = User::factory()->therapist()->create();
        $tile = Tile::whereNull('user_id')->first();
        $this->actingAs($therapist)->post("/aac/tiles/{$tile->id}/audio", ['ta_audio' => $this->voice()], ['Accept' => 'application/json'])->assertOk();
        $path = $tile->fresh()->ta_audio_path;
        Storage::disk('aac-recordings')->put($path, 'real bytes');

        try {
            $response = $this->actingAs(User::factory()->create())->get("/aac/tiles/{$tile->id}/audio")->assertOk();

            $served = $response->baseResponse->getFile()->getPathname();
            $this->assertSame(realpath($cache), realpath(dirname($served)));
            $this->assertSame('real bytes', file_get_contents($served));
            $this->assertDirectoryDoesNotExist(storage_path('framework/cache/aac-audio'));
        } finally {
            File::deleteDirectory($cache);
        }
    }

    public function test_a_parents_own_recording_stays_private_on_the_bucket(): void
    {
        $parent = User::factory()->create();
        $id = $this->actingAs($parent)->post('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->value('id'),
            'label_en' => 'water',
            'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertCreated()->json('tile.id');

        Storage::disk('aac-recordings')->assertExists(Tile::find($id)->ta_audio_path);
        $this->actingAs(User::factory()->create())->get("/aac/tiles/{$id}/audio")->assertForbidden();
        $this->actingAs($parent)->get("/aac/tiles/{$id}/audio")->assertOk();
    }

    public function test_photos_are_stored_on_the_photo_bucket(): void
    {
        $parent = User::factory()->create();
        $response = $this->actingAs($parent)->post('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->value('id'),
            'label_en' => 'dosa',
            'photo' => UploadedFile::fake()->image('dosa.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $tile = Tile::find($response->json('tile.id'));
        Storage::disk('aac-photos')->assertExists($tile->image_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_deleting_a_word_removes_its_files_from_the_buckets(): void
    {
        $parent = User::factory()->create();
        $id = $this->actingAs($parent)->post('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->value('id'),
            'label_en' => 'dosa',
            'photo' => UploadedFile::fake()->image('dosa.jpg'),
            'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertCreated()->json('tile.id');
        $tile = Tile::find($id);

        $this->actingAs($parent)->deleteJson("/aac/tiles/{$id}")->assertNoContent();

        Storage::disk('aac-photos')->assertMissing($tile->image_path);
        Storage::disk('aac-recordings')->assertMissing($tile->ta_audio_path);
    }
}
