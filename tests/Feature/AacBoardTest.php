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

class AacBoardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/board')->assertRedirect('/login');
    }

    public function test_board_loads_categories_without_a_core_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/board')
            ->assertOk()
            ->assertViewHas('payload', function (array $payload) {
                return ! array_key_exists('core', $payload)
                    && collect($payload['categories'])->whereNull('parent')->count() === 13
                    && $payload['settings']['lang'] === 'en';
            });
    }

    public function test_quick_talk_starts_with_high_frequency_words(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/board')
            ->assertOk()
            ->assertViewHas('payload', function (array $payload) {
                $quick = collect($payload['categories'])->firstWhere('slug', 'quick');
                $first = collect($quick['tiles'])->take(5);

                // எனக்கு and நான் stay separate tiles, both shown as "I" in English.
                return $first->pluck('ta')->all() === ['எனக்கு', 'வேண்டும்', 'வேண்டாம்', 'நான்', 'நீங்கள்']
                    && $first->pluck('en')->all() === ['I', 'Want', "Don't want", 'I', 'You']
                    && $first->every(fn ($t) => $t['k'] === 'phrase' && $t['frame'] === null);
            });
    }

    public function test_quick_talk_holds_the_sentence_starters_and_essential_words(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/board')
            ->assertOk()
            ->assertViewHas('payload', function (array $payload) {
                $tiles = collect(collect($payload['categories'])->firstWhere('slug', 'quick')['tiles']);
                $frames = $tiles->whereNotNull('frame');
                $need = $frames->firstWhere('frame.key', 'need');

                return $frames->pluck('frame.key')->all() === ['want', 'need', 'dontwant', 'go', 'like', 'where']
                    && $need['frame']['en'] === 'I need {}'
                    && $need['frame']['ta'] === 'எனக்கு {} தேவை'
                    && $need['en'] === 'I need …'
                    && collect(['yes', 'no', 'help', 'stop', 'please', 'Hello', 'Good morning', 'Thank you', 'Sorry', 'I need the toilet'])
                        ->every(fn ($w) => $tiles->contains('en', $w))
                    && Tile::whereNull('category_id')->doesntExist();
            });
    }

    public function test_built_in_quick_talk_words_come_before_user_added_ones(): void
    {
        $user = User::factory()->create();
        $quick = Category::where('slug', 'quick')->first();

        $this->actingAs($user)->postJson('/aac/tiles', [
            'category_id' => $quick->id,
            'label_en' => 'My name is Arun',
            'label_ta' => 'என் பெயர் அருண்',
        ])->assertCreated();

        $this->actingAs($user)->get('/board')->assertViewHas('payload', function (array $payload) {
            $tiles = collect(collect($payload['categories'])->firstWhere('slug', 'quick')['tiles']);

            return $tiles->last()['en'] === 'My name is Arun'
                && $tiles->slice(0, -1)->every(fn ($t) => ! $t['custom']);
        });
    }

    public function test_user_can_add_a_place_with_its_tamil_to_form(): void
    {
        $user = User::factory()->create();
        $places = Category::where('slug', 'places')->first();

        $this->actingAs($user)->postJson('/aac/tiles', [
            'category_id' => $places->id,
            'label_en' => "grandma's house",
            'label_ta' => 'பாட்டி வீடு',
            'ta_dative' => 'பாட்டி வீட்டுக்கு',
            'emoji' => '🏡',
        ])
            ->assertCreated()
            ->assertJsonPath('tile.k', 'place')
            ->assertJsonPath('tile.dat', 'பாட்டி வீட்டுக்கு')
            ->assertJsonPath('tile.custom', true);
    }

    public function test_user_can_add_a_word_with_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $people = Category::where('slug', 'people')->first();

        $response = $this->actingAs($user)->post('/aac/tiles', [
            'category_id' => $people->id,
            'label_ta' => 'மாமா',
            'photo' => UploadedFile::fake()->image('uncle.jpg', 320, 320),
        ], ['Accept' => 'application/json'])->assertCreated();

        $tile = Tile::find($response->json('tile.id'));
        $this->assertSame('மாமா', $tile->label_en); // falls back to the Tamil word
        Storage::disk('public')->assertExists($tile->image_path);
    }

    public function test_a_word_needs_english_or_tamil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/aac/tiles', [
            'category_id' => Category::first()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['label_en', 'label_ta']);
    }

    public function test_built_in_tiles_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $builtIn = Tile::whereNull('user_id')->first();

        $this->actingAs($user)->deleteJson("/aac/tiles/{$builtIn->id}")->assertForbidden();
    }

    public function test_users_cannot_delete_each_others_words(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tile = $owner->tiles()->create([
            'category_id' => Category::first()->id, 'kind' => 'noun',
            'label_en' => 'kite', 'label_ta' => 'பட்டம்',
        ]);

        $this->actingAs($other)->deleteJson("/aac/tiles/{$tile->id}")->assertForbidden();
        $this->actingAs($owner)->deleteJson("/aac/tiles/{$tile->id}")->assertNoContent();
    }

    public function test_custom_words_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->tiles()->create([
            'category_id' => Category::first()->id, 'kind' => 'phrase',
            'label_en' => 'My dog is Tiger', 'label_ta' => 'என் நாயின் பெயர் டைகர்',
        ]);

        $this->actingAs($other)->get('/board')->assertViewHas('payload', function (array $payload) {
            return collect($payload['categories'])->pluck('tiles')->flatten(1)
                ->doesntContain(fn ($t) => $t['en'] === 'My dog is Tiger');
        });
    }

    public function test_settings_are_validated_and_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/aac/settings', ['lang' => 'fr'])->assertUnprocessable();

        $this->actingAs($user)->putJson('/aac/settings', ['lang' => 'ta', 'cols' => 4])
            ->assertOk()
            ->assertJsonPath('settings.lang', 'ta')
            ->assertJsonPath('settings.cols', 4);
    }

    public function test_usage_is_not_logged_when_history_is_off(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/aac/settings', ['log_usage' => false]);

        $this->actingAs($user)->postJson('/aac/usage', ['sentence' => 'எனக்கு தண்ணீர் வேண்டும்', 'lang' => 'ta'])
            ->assertNoContent();

        $this->assertDatabaseCount('usage_logs', 0);
    }

    public function test_saved_phrases_round_trip(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson('/aac/phrases', ['text' => 'வணக்கம் டாக்டர்'])
            ->assertCreated()->json('phrase.id');

        $this->actingAs($user)->deleteJson("/aac/phrases/{$id}")->assertNoContent();
        $this->assertDatabaseCount('saved_phrases', 0);
    }
}
