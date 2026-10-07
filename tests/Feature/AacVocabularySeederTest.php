<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AacVocabularySeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    /** The real seeder with its word list changed by $change(array $categories): array. */
    private function seederWith(callable $change): AacVocabularySeeder
    {
        return new class($change) extends AacVocabularySeeder
        {
            public function __construct(private $change) {}

            protected function categories(): array
            {
                return ($this->change)(parent::categories());
            }
        };
    }

    private function snapshot(): array
    {
        return Tile::orderBy('id')->get()->map->getAttributes()->all();
    }

    private function quickTile(int $i): Tile
    {
        return Tile::whereNull('user_id')
            ->where('category_id', Category::where('slug', 'quick')->value('id'))
            ->orderBy('sort_order')->skip($i)->firstOrFail();
    }

    public function test_every_built_in_word_has_a_unique_seed_key(): void
    {
        $builtIn = Tile::whereNull('user_id');

        $this->assertSame(0, (clone $builtIn)->whereNull('seed_key')->count());
        $this->assertSame((clone $builtIn)->count(), (clone $builtIn)->distinct()->count('seed_key'));
        $this->assertSame('quick|I|எனக்கு', $this->quickTile(0)->seed_key);
    }

    public function test_re_seeding_keeps_ids_recordings_and_leaves_unchanged_words_untouched(): void
    {
        $this->quickTile(0)->forceFill(['ta_audio_path' => 'aac/audio/shared/enakku.webm'])->save();
        $before = $this->snapshot();

        $this->travel(5)->minutes();
        (new AacVocabularySeeder)->run();

        // Same ids, same recordings, same updated_at: nothing was deleted, re-created or rewritten.
        $this->assertSame($before, $this->snapshot());
    }

    public function test_re_seeding_never_touches_custom_words(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson('/aac/tiles', [
            'category_id' => Category::where('slug', 'quick')->value('id'),
            'label_en' => 'I',
            'label_ta' => 'எனக்கு',
        ])->assertCreated()->json('tile.id');
        $before = Tile::find($id)->getAttributes();

        (new AacVocabularySeeder)->run();

        $this->assertSame($before, Tile::find($id)->getAttributes());
        $this->assertNull(Tile::find($id)->seed_key);
    }

    public function test_a_removed_word_with_a_recording_stops_the_seeder_before_any_change(): void
    {
        $this->quickTile(0)->forceFill(['ta_audio_path' => 'aac/audio/shared/enakku.webm'])->save();
        $before = $this->snapshot();

        $seeder = $this->seederWith(function (array $cats) {
            array_shift($cats[0]['tiles']);   // remove the recorded word
            $cats[0]['tiles'][0][1] = 'Want!'; // and change another, which must not be written either

            return $cats;
        });

        try {
            $seeder->run();
            $this->fail('The seeder should have stopped.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('quick|I|எனக்கு', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_removed_word_without_a_recording_is_deleted(): void
    {
        $removed = $this->quickTile(0);
        $count = Tile::count();

        $this->seederWith(function (array $cats) {
            array_shift($cats[0]['tiles']);

            return $cats;
        })->run();

        $this->assertNull(Tile::find($removed->id));
        $this->assertSame($count - 1, Tile::count());
    }

    public function test_duplicate_seed_keys_stop_the_seeder_before_any_change(): void
    {
        $before = $this->snapshot();

        $this->expectException(RuntimeException::class);
        try {
            $this->seederWith(function (array $cats) {
                $cats[0]['tiles'][] = $cats[0]['tiles'][0];

                return $cats;
            })->run();
        } finally {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_a_renamed_word_keeps_its_id_and_recording_with_its_old_key(): void
    {
        $tile = $this->quickTile(0);
        $tile->forceFill(['ta_audio_path' => 'aac/audio/shared/enakku.webm'])->save();

        $this->seederWith(function (array $cats) {
            $cats[0]['tiles'][0] = ['🙋', 'Me', 'எனக்கு', 'key' => 'quick|I|எனக்கு'];

            return $cats;
        })->run();

        $tile->refresh();
        $this->assertSame('Me', $tile->label_en);
        $this->assertSame('aac/audio/shared/enakku.webm', $tile->ta_audio_path);
    }

    public function test_a_new_seeder_word_is_added_without_a_recording(): void
    {
        $count = Tile::count();

        $this->seederWith(function (array $cats) {
            $cats[0]['tiles'][] = ['🧃', 'juice box', 'ஜூஸ்'];

            return $cats;
        })->run();

        $tile = Tile::where('seed_key', 'quick|juice box|ஜூஸ்')->firstOrFail();
        $this->assertSame($count + 1, Tile::count());
        $this->assertNull($tile->user_id);
        $this->assertNull($tile->ta_audio_path);
    }
}
