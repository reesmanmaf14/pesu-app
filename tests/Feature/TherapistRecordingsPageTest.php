<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistRecordingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    private function listedIds(User $user, string $url = '/therapist/recordings'): array
    {
        return collect($this->actingAs($user)->get($url)->assertOk()->viewData('payload')['categories'])
            ->flatMap(fn ($c) => collect($c['tiles'])->pluck('id'))
            ->sort()->values()->all();
    }

    public function test_parents_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/therapist/recordings')->assertForbidden();
    }

    public function test_it_lists_every_built_in_word(): void
    {
        $this->assertSame(
            Tile::whereNull('user_id')->orderBy('id')->pluck('id')->all(),
            $this->listedIds(User::factory()->therapist()->create())
        );
    }

    public function test_it_never_lists_a_parents_own_words(): void
    {
        $parent = User::factory()->create();
        $id = $this->actingAs($parent)->postJson('/aac/tiles', [
            'category_id' => Category::where('slug', 'food')->first()->id,
            'label_en' => 'Grandma’s dosa',
        ])->assertCreated()->json('tile.id');

        $therapist = User::factory()->therapist()->create();
        $this->assertNotContains($id, $this->listedIds($therapist));
        $this->actingAs($therapist)->get('/therapist/recordings')->assertDontSee('Grandma');
    }

    public function test_the_missing_filter_hides_recorded_words(): void
    {
        $recorded = Tile::whereNull('user_id')->first();
        $recorded->forceFill(['ta_audio_path' => 'aac/audio/shared/x.webm'])->save();

        $ids = $this->listedIds(User::factory()->therapist()->create(), '/therapist/recordings?missing=1');

        $this->assertNotContains($recorded->id, $ids);
        $this->assertCount(Tile::whereNull('user_id')->count() - 1, $ids);
    }
}
