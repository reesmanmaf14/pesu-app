<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AacSubcategoryTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    private const FOOD_SUBCATEGORIES = [
        'food-vegetables', 'food-fruits', 'food-meals', 'food-drinks', 'food-snacks', 'food-desserts',
    ];

    public function test_food_and_drink_has_its_subcategories(): void
    {
        $food = Category::where('slug', 'food')->first();

        $this->assertNull($food->parent_id);
        $this->assertSame(self::FOOD_SUBCATEGORIES, $food->children()->orderBy('sort_order')->pluck('slug')->all());
    }

    public function test_board_sends_subcategories_with_their_parent(): void
    {
        $user = User::factory()->create();
        $food = Category::where('slug', 'food')->first();

        $this->actingAs($user)->get('/board')->assertViewHas('payload', function (array $payload) use ($food) {
            $cats = collect($payload['categories']);
            $fruits = $cats->firstWhere('slug', 'food-fruits');

            return $cats->whereIn('slug', self::FOOD_SUBCATEGORIES)->every(fn ($c) => $c['parent'] === $food->id)
                && $cats->firstWhere('slug', 'food')['parent'] === null
                && collect($fruits['tiles'])->pluck('en')->all() === ['banana', 'apple', 'mango', 'orange', 'watermelon', 'grapes'];
        });
    }

    public function test_food_and_drink_keeps_the_food_phrases_and_foods_live_in_subcategories(): void
    {
        $food = Category::where('slug', 'food')->first();
        $phrases = $food->tiles()->orderBy('sort_order')->get();

        $this->assertTrue($phrases->every(fn ($t) => $t->kind === 'phrase'));
        $this->assertSame('பசிக்குது', $phrases->firstWhere('label_en', 'Hungry')->label_ta);
        $this->assertSame('ஊட்டி விடு', $phrases->firstWhere('label_en', 'Feed me')->label_ta);
        $this->assertFalse($phrases->contains('label_en', 'banana'));

        $meals = Category::where('slug', 'food-meals')->first();
        $this->assertTrue($meals->tiles()->where('label_en', 'egg')->exists());
        $this->assertSame('noun', $meals->tiles()->first()->kind);
    }

    public function test_user_can_add_a_word_to_a_subcategory(): void
    {
        $user = User::factory()->create();
        $fruits = Category::where('slug', 'food-fruits')->first();

        $this->actingAs($user)->postJson('/aac/tiles', [
            'category_id' => $fruits->id,
            'label_en' => 'jackfruit',
            'label_ta' => 'பலாப்பழம்',
        ])
            ->assertCreated()
            ->assertJsonPath('tile.cat', $fruits->id)
            ->assertJsonPath('tile.k', 'noun');
    }

    public function test_reseeding_keeps_user_words_in_food_and_its_subcategories(): void
    {
        $user = User::factory()->create();
        $food = Category::where('slug', 'food')->first();
        $fruits = Category::where('slug', 'food-fruits')->first();

        $mine = collect([$food, $fruits])->map(fn (Category $c) => $user->tiles()->create([
            'category_id' => $c->id, 'kind' => 'noun', 'label_en' => "my {$c->slug}", 'label_ta' => 'என்',
        ]));

        $this->seed(AacVocabularySeeder::class);

        $this->assertSame($food->id, Category::where('slug', 'food')->value('id'));
        $this->assertSame($fruits->id, Category::where('slug', 'food-fruits')->value('id'));
        $mine->each(fn (Tile $t) => $this->assertSame($t->category_id, $t->fresh()->category_id));
        $this->assertSame(6, Category::where('parent_id', $food->id)->count());
    }
}
