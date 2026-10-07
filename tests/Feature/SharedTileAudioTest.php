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

/** The therapist's shared Tamil recordings of built-in words, and parents' own recordings via the audio endpoint. */
class SharedTileAudioTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    /** Everything about a word except its recording. */
    private const WORD_FIELDS = [
        'id', 'user_id', 'seed_key', 'category_id', 'is_core', 'kind', 'emoji', 'image_path',
        'label_en', 'label_ta', 'ta_dative', 'ta_infinitive', 'en_to', 'en_infinitive', 'en_article',
        'frame_key', 'template_en', 'template_ta', 'accepts', 'sort_order', 'created_at',
    ];

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

    /** A built-in sentence starter: the tile with the most grammar fields to protect. */
    private function builtIn(): Tile
    {
        return Tile::whereNull('user_id')->whereNotNull('frame_key')->firstOrFail();
    }

    private function record(User $user, Tile $tile, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post("/aac/tiles/{$tile->id}/audio", ['ta_audio' => $this->voice(), ...$extra], ['Accept' => 'application/json']);
    }

    private function wordFields(Tile $tile): array
    {
        return array_intersect_key($tile->fresh()->getAttributes(), array_flip(self::WORD_FIELDS));
    }

    public function test_the_therapist_can_record_a_built_in_word(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();

        $this->record($therapist, $tile)->assertOk()
            ->assertJsonPath('tile.id', $tile->id)
            ->assertJsonPath('tile.custom', false);

        $path = $tile->fresh()->ta_audio_path;
        $this->assertStringStartsWith('aac/audio/shared/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_recording_a_built_in_word_changes_only_its_recording(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();
        $before = $this->wordFields($tile);
        $otherWords = Tile::whereKeyNot($tile->id)->orderBy('id')->get()->map->getAttributes()->all();

        // Extra fields are ignored: only the recording is read.
        $this->record($therapist, $tile, [
            'label_en' => 'changed', 'label_ta' => 'changed', 'category_id' => Category::first()->id,
            'kind' => 'noun', 'emoji' => '❌', 'sort_order' => 99, 'user_id' => $therapist->id,
        ])->assertOk();
        $this->assertSame($before, $this->wordFields($tile));

        $this->actingAs($therapist)->post("/aac/tiles/{$tile->id}/audio", ['ta_audio' => $this->voice()], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($therapist)->deleteJson("/aac/tiles/{$tile->id}/audio")->assertOk();
        $this->assertSame($before, $this->wordFields($tile));

        $this->assertSame($otherWords, Tile::whereKeyNot($tile->id)->orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_the_therapist_can_replace_and_remove_a_shared_recording(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();

        $this->record($therapist, $tile)->assertOk();
        $old = $tile->fresh()->ta_audio_path;

        $this->record($therapist, $tile)->assertOk();
        $new = $tile->fresh()->ta_audio_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);

        $this->actingAs($therapist)->deleteJson("/aac/tiles/{$tile->id}/audio")
            ->assertOk()
            ->assertJsonPath('tile.audio', null);
        $this->assertNull($tile->fresh()->ta_audio_path);
        Storage::disk('local')->assertMissing($new);
    }

    public function test_a_failed_upload_keeps_the_existing_recording(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();
        $this->record($therapist, $tile)->assertOk();
        $path = $tile->fresh()->ta_audio_path;

        $this->actingAs($therapist)->post("/aac/tiles/{$tile->id}/audio", [
            'ta_audio' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['ta_audio']);

        $this->assertSame($path, $tile->fresh()->ta_audio_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_a_recording_is_required(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)->postJson("/aac/tiles/{$this->builtIn()->id}/audio", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ta_audio']);
    }

    public function test_parents_cannot_record_or_remove_a_shared_recording(): void
    {
        $therapist = User::factory()->therapist()->create();
        $parent = User::factory()->create();
        $tile = $this->builtIn();
        $this->record($therapist, $tile)->assertOk();
        $path = $tile->fresh()->ta_audio_path;

        $this->record($parent, $tile)->assertForbidden();
        $this->actingAs($parent)->deleteJson("/aac/tiles/{$tile->id}/audio")->assertForbidden();
        // The normal word editor still refuses built-in words too.
        $this->actingAs($parent)->post("/aac/tiles/{$tile->id}", [
            '_method' => 'PUT', 'category_id' => $tile->category_id, 'label_en' => 'x', 'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertSame($path, $tile->fresh()->ta_audio_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_the_therapist_cannot_edit_or_delete_a_built_in_word(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();

        $this->actingAs($therapist)->putJson("/aac/tiles/{$tile->id}", ['category_id' => $tile->category_id, 'label_en' => 'x'])->assertForbidden();
        $this->actingAs($therapist)->deleteJson("/aac/tiles/{$tile->id}")->assertForbidden();
        $this->assertNotNull($tile->fresh());
    }

    public function test_approved_parents_can_play_a_shared_recording(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();
        $this->record($therapist, $tile)->assertOk();

        $audio = $this->actingAs(User::factory()->create())->get('/board')->viewData('payload')['categories']
            ->flatMap(fn ($c) => $c['tiles'])->firstWhere('id', $tile->id)['audio'];
        $this->assertStringContainsString("/aac/tiles/{$tile->id}/audio", $audio);

        $this->actingAs(User::factory()->create())->get("/aac/tiles/{$tile->id}/audio")
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/webm');
    }

    public function test_pending_users_and_guests_cannot_play_a_shared_recording(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();
        $this->record($therapist, $tile)->assertOk();

        $this->actingAs(User::factory()->pending()->create())->get("/aac/tiles/{$tile->id}/audio")->assertRedirect('/account/status');
        auth()->logout();
        $this->get("/aac/tiles/{$tile->id}/audio")->assertRedirect('/login');
    }

    public function test_the_therapist_cannot_hear_or_record_a_parents_own_word(): void
    {
        $therapist = User::factory()->therapist()->create();
        $parent = User::factory()->create();
        $id = $this->actingAs($parent)->post('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->first()->id,
            'label_en' => 'water',
            'ta_audio' => $this->voice(),
        ], ['Accept' => 'application/json'])->assertCreated()->json('tile.id');
        $path = Tile::find($id)->ta_audio_path;

        $this->actingAs($therapist)->get("/aac/tiles/{$id}/audio")->assertForbidden();
        $this->record($therapist, Tile::find($id))->assertForbidden();
        $this->actingAs($therapist)->deleteJson("/aac/tiles/{$id}/audio")->assertForbidden();
        $this->assertSame($path, Tile::find($id)->ta_audio_path);
    }

    public function test_parents_can_record_their_own_word_through_the_audio_endpoint(): void
    {
        $parent = User::factory()->create();
        $id = $this->actingAs($parent)->postJson('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->first()->id,
            'label_en' => 'water',
        ])->assertCreated()->json('tile.id');

        $this->record($parent, Tile::find($id))->assertOk()->assertJsonPath('tile.custom', true);

        $this->assertStringStartsWith("aac/audio/{$parent->id}/", Tile::find($id)->ta_audio_path);
        $this->actingAs($parent)->get("/aac/tiles/{$id}/audio")->assertOk();
        $this->actingAs(User::factory()->create())->get("/aac/tiles/{$id}/audio")->assertForbidden();
    }

    public function test_recording_never_creates_a_word(): void
    {
        $therapist = User::factory()->therapist()->create();
        $count = Tile::count();

        $this->record($therapist, $this->builtIn())->assertOk();
        $this->record($therapist, $this->builtIn())->assertOk();

        $this->assertSame($count, Tile::count());
    }

    public function test_a_shared_recording_survives_deleting_the_therapist_account(): void
    {
        $therapist = User::factory()->therapist()->create();
        $tile = $this->builtIn();
        $this->record($therapist, $tile)->assertOk();
        $path = $tile->fresh()->ta_audio_path;

        $therapist->delete();

        $this->assertSame($path, $tile->fresh()->ta_audio_path);
        Storage::disk('local')->assertExists($path);
    }
}
