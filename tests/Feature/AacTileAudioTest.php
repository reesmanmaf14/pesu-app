<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AacTileAudioTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function voice(string $name = 'voice.webm', int $kb = 20, string $type = 'audio/webm'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, $type);
    }

    private function addWord(User $user, array $extra = []): Tile
    {
        $id = $this->actingAs($user)->post('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->first()->id,
            'label_en' => 'water',
            'label_ta' => 'தண்ணீர்',
            ...$extra,
        ], ['Accept' => 'application/json'])->assertCreated()->json('tile.id');

        return Tile::find($id);
    }

    public function test_a_word_can_be_saved_with_a_tamil_recording(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->first()->id,
            'label_en' => 'water',
            'label_ta' => 'தண்ணீர்',
            'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertCreated();

        $tile = Tile::find($response->json('tile.id'));
        $this->assertStringStartsWith("aac/audio/{$user->id}/", $tile->ta_audio_path);
        $this->assertStringEndsWith('.webm', $tile->ta_audio_path);
        Storage::disk('local')->assertExists($tile->ta_audio_path);
        // Private: never on the public disk.
        Storage::disk('public')->assertMissing($tile->ta_audio_path);
        $this->assertStringContainsString("/aac/tiles/{$tile->id}/audio", $response->json('tile.audio'));
    }

    public function test_words_without_a_recording_have_no_audio_url(): void
    {
        $tile = $this->addWord(User::factory()->create());

        $this->assertNull($tile->ta_audio_path);
        $this->assertNull($tile->toAac()['audio']);
    }

    public function test_the_browser_filename_is_not_trusted(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user, ['ta_audio' => $this->voice('../../../voice.exe', 20, 'video/webm')]);

        $this->assertMatchesRegularExpression("#^aac/audio/{$user->id}/[A-Za-z0-9]{40}\.webm$#", $tile->ta_audio_path);
    }

    public function test_php_filenames_are_rejected_even_with_an_audio_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/aac/tiles', [
            'category_id' => Category::first()->id,
            'label_en' => 'water',
            'ta_audio' => $this->voice('evil.php'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['ta_audio']);

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_safari_mp4_recordings_are_accepted(): void
    {
        $tile = $this->addWord(User::factory()->create(), ['ta_audio' => $this->voice('voice.mp4', 30, 'audio/mp4')]);

        $this->assertStringEndsWith('.m4a', $tile->ta_audio_path);
    }

    public function test_non_audio_files_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/aac/tiles', [
            'category_id' => Category::first()->id,
            'label_en' => 'water',
            'ta_audio' => UploadedFile::fake()->create('voice.webm', 5, 'text/html'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ta_audio']);

        $this->assertSame(0, Tile::whereNotNull('user_id')->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_oversized_recordings_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/aac/tiles', [
            'category_id' => Category::first()->id,
            'label_en' => 'water',
            'ta_audio' => $this->voice('voice.webm', 1025),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ta_audio']);

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_the_owner_can_play_their_recording(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user, ['ta_audio' => $this->voice()]);

        $this->actingAs($user)->get("/aac/tiles/{$tile->id}/audio")
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/webm');
    }

    public function test_other_users_and_guests_cannot_play_a_recording(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tile = $this->addWord($owner, ['ta_audio' => $this->voice()]);

        $this->actingAs($other)->get("/aac/tiles/{$tile->id}/audio")->assertForbidden();

        auth()->logout();
        $this->get("/aac/tiles/{$tile->id}/audio")->assertRedirect('/login');
    }

    public function test_a_word_without_a_recording_returns_not_found(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user);

        $this->actingAs($user)->get("/aac/tiles/{$tile->id}/audio")->assertNotFound();
    }

    public function test_re_recording_replaces_the_old_file(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user, ['ta_audio' => $this->voice()]);
        $old = $tile->ta_audio_path;

        $this->actingAs($user)->post("/aac/tiles/{$tile->id}", [
            '_method' => 'PUT',
            'category_id' => $tile->category_id,
            'label_en' => 'water',
            'label_ta' => 'தண்ணீர்',
            'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertOk();

        $new = $tile->fresh()->ta_audio_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_a_recording_can_be_removed(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user, ['ta_audio' => $this->voice()]);
        $path = $tile->ta_audio_path;

        $this->actingAs($user)->putJson("/aac/tiles/{$tile->id}", [
            'category_id' => $tile->category_id,
            'label_en' => 'water',
            'remove_audio' => true,
        ])->assertOk()->assertJsonPath('tile.audio', null);

        $this->assertNull($tile->fresh()->ta_audio_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_another_user_cannot_replace_or_remove_a_recording(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $tile = $this->addWord($owner, ['ta_audio' => $this->voice()]);
        $path = $tile->ta_audio_path;

        $this->actingAs($attacker)->post("/aac/tiles/{$tile->id}", [
            '_method' => 'PUT',
            'category_id' => $tile->category_id,
            'label_en' => 'water',
            'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAs($attacker)->putJson("/aac/tiles/{$tile->id}", [
            'category_id' => $tile->category_id,
            'label_en' => 'water',
            'remove_audio' => true,
        ])->assertForbidden();

        $this->actingAs($attacker)->deleteJson("/aac/tiles/{$tile->id}")->assertForbidden();

        $this->assertSame($path, $tile->fresh()->ta_audio_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_deleting_a_word_deletes_its_recording(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user, ['ta_audio' => $this->voice()]);
        $path = $tile->ta_audio_path;

        $this->actingAs($user)->deleteJson("/aac/tiles/{$tile->id}")->assertNoContent();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_an_account_deletes_its_recordings(): void
    {
        $user = User::factory()->create();
        $tile = $this->addWord($user, ['ta_audio' => $this->voice()]);

        $user->delete();

        Storage::disk('local')->assertMissing($tile->ta_audio_path);
    }
}
