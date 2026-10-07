<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AacCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    private function categoryFor(User $user, array $attrs = []): Category
    {
        return $user->categories()->create([
            'slug' => 'my-'.uniqid(),
            'name_en' => 'Therapy words',
            'name_ta' => 'சிகிச்சை சொற்கள்',
            'default_kind' => 'noun',
            'emoji' => '🧩',
            ...$attrs,
        ]);
    }

    private function wordIn(Category $category, User $user, array $attrs = []): Tile
    {
        return $user->tiles()->create([
            'category_id' => $category->id, 'kind' => $category->default_kind,
            'label_en' => 'water', 'label_ta' => 'தண்ணீர்', ...$attrs,
        ]);
    }

    public function test_user_can_create_a_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/aac/categories', [
            'name_en' => 'My Classroom',
            'name_ta' => 'என் வகுப்பு',
            'emoji' => '🏫',
            'kind' => 'noun',
        ])
            ->assertCreated()
            ->assertJsonPath('category.en', 'My Classroom')
            ->assertJsonPath('category.k', 'noun')
            ->assertJsonPath('category.custom', true);

        $category = Category::where('name_en', 'My Classroom')->first();
        $this->assertSame($user->id, $category->user_id);
        $this->assertStringStartsWith('my-', $category->slug);
    }

    public function test_a_category_name_in_one_language_is_enough(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/aac/categories', ['name_ta' => 'நண்பர்கள்', 'kind' => 'person'])
            ->assertCreated()
            ->assertJsonPath('category.en', 'நண்பர்கள்')
            ->assertJsonPath('category.e', '📁');
    }

    public function test_category_input_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/aac/categories', ['kind' => 'noun'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name_en', 'name_ta']);

        $this->actingAs($user)->postJson('/aac/categories', ['name_en' => 'Frames', 'kind' => 'frame'])
            ->assertUnprocessable()->assertJsonValidationErrors(['kind']);

        $this->actingAs($user)->postJson('/aac/categories', ['name_en' => str_repeat('a', 61), 'kind' => 'noun'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name_en']);
    }

    public function test_guests_cannot_create_categories(): void
    {
        $this->postJson('/aac/categories', ['name_en' => 'Food', 'kind' => 'noun'])->assertUnauthorized();
        $this->assertDatabaseMissing('categories', ['name_en' => 'Food', 'user_id' => null, 'slug' => 'food-x']);
        $this->assertSame(0, Category::whereNotNull('user_id')->count());
    }

    public function test_users_see_their_own_categories_but_not_other_peoples(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->categoryFor($owner, ['name_en' => 'Secret things']);

        $this->actingAs($owner)->get('/board')->assertViewHas('payload', function (array $payload) {
            $cats = collect($payload['categories']);

            return $cats->whereNull('parent')->count() === 14
                && $cats->last()['en'] === 'Secret things' // own categories come after built-in ones
                && $cats->last()['custom'] === true;
        });

        $this->actingAs($other)->get('/board')->assertViewHas('payload', function (array $payload) {
            return collect($payload['categories'])->whereNull('parent')->count() === 13
                && collect($payload['categories'])->doesntContain('en', 'Secret things');
        });
    }

    public function test_user_can_edit_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user);

        $this->actingAs($user)->putJson("/aac/categories/{$category->id}", [
            'name_en' => 'Daily routine', 'name_ta' => 'தினசரி', 'emoji' => '⏰', 'kind' => 'noun',
        ])->assertOk()->assertJsonPath('category.en', 'Daily routine');

        $this->assertSame('Daily routine', $category->fresh()->name_en);
    }

    public function test_changing_a_category_kind_updates_its_words(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user);
        $tile = $this->wordIn($category, $user, ['label_en' => 'beach', 'label_ta' => 'கடற்கரை']);

        $this->actingAs($user)->putJson("/aac/categories/{$category->id}", [
            'name_en' => 'Fun places', 'kind' => 'place',
        ])->assertOk()->assertJsonPath('category.tiles.0.k', 'place');

        $this->assertSame('place', $tile->fresh()->kind);
        $this->assertSame('to beach', $tile->fresh()->en_to);
    }

    public function test_users_cannot_edit_or_delete_each_others_categories(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = $this->categoryFor($owner);

        $this->actingAs($other)->putJson("/aac/categories/{$category->id}", ['name_en' => 'Hacked', 'kind' => 'noun'])
            ->assertForbidden();
        $this->actingAs($other)->deleteJson("/aac/categories/{$category->id}")->assertForbidden();

        $this->assertSame('Therapy words', $category->fresh()->name_en);
    }

    public function test_built_in_categories_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create();
        $food = Category::where('slug', 'food')->first();

        $this->actingAs($user)->putJson("/aac/categories/{$food->id}", ['name_en' => 'Snacks', 'kind' => 'noun'])
            ->assertForbidden();
        $this->actingAs($user)->deleteJson("/aac/categories/{$food->id}")->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $food->id, 'name_en' => 'Food & drink']);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user);

        $this->actingAs($user)->deleteJson("/aac/categories/{$category->id}")->assertNoContent();
        $this->assertModelMissing($category);
    }

    public function test_a_category_with_words_is_not_deleted_and_its_words_are_kept(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user);
        $tile = $this->wordIn($category, $user);

        $this->actingAs($user)->deleteJson("/aac/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This category still has 1 word. Move or delete it first.');

        $this->assertModelExists($category);
        $this->assertModelExists($tile);
    }

    public function test_user_can_add_a_word_to_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user, ['default_kind' => 'person']);

        $id = $this->actingAs($user)->postJson('/aac/tiles', [
            'category_id' => $category->id,
            'label_en' => 'Priya',
            'label_ta' => 'பிரியா',
        ])
            ->assertCreated()
            ->assertJsonPath('tile.cat', $category->id)
            ->assertJsonPath('tile.k', 'person')
            ->json('tile.id');

        $tile = Tile::find($id);
        $this->assertSame($user->id, $tile->user_id);
        $this->assertSame($category->id, $tile->category_id);
    }

    public function test_user_cannot_add_a_word_to_someone_elses_category(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $category = $this->categoryFor($owner);

        $this->actingAs($attacker)->postJson('/aac/tiles', [
            'category_id' => $category->id,
            'label_en' => 'spam',
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);

        $this->assertSame(0, $category->tiles()->count());
    }

    public function test_user_can_edit_and_move_their_own_word(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user);
        $tile = $this->wordIn($category, $user);
        $actions = Category::where('slug', 'actions')->first();

        $this->actingAs($user)->putJson("/aac/tiles/{$tile->id}", [
            'category_id' => $actions->id,
            'label_en' => 'swim',
            'label_ta' => 'நீந்து',
            'ta_infinitive' => 'நீந்த',
        ])
            ->assertOk()
            ->assertJsonPath('tile.en', 'swim')
            ->assertJsonPath('tile.k', 'verb')
            ->assertJsonPath('tile.inf', 'நீந்த')
            ->assertJsonPath('tile.cat', $actions->id);
    }

    public function test_user_cannot_edit_someone_elses_word_or_a_built_in_word(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tile = $this->wordIn(Category::first(), $owner);
        $builtIn = Tile::whereNull('user_id')->whereNotNull('category_id')->first();
        $body = ['category_id' => Category::first()->id, 'label_en' => 'changed'];

        $this->actingAs($other)->putJson("/aac/tiles/{$tile->id}", $body)->assertForbidden();
        $this->actingAs($other)->putJson("/aac/tiles/{$builtIn->id}", $body)->assertForbidden();

        $this->assertSame('water', $tile->fresh()->label_en);
        $this->assertNotSame('changed', $builtIn->fresh()->label_en);
    }

    public function test_user_cannot_move_their_word_into_someone_elses_category(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $private = $this->categoryFor($owner);
        $tile = $this->wordIn(Category::first(), $user);

        $this->actingAs($user)->putJson("/aac/tiles/{$tile->id}", [
            'category_id' => $private->id, 'label_en' => 'water',
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);

        $this->assertSame(Category::first()->id, $tile->fresh()->category_id);
    }

    public function test_reseeding_keeps_user_categories_and_their_words(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryFor($user);
        $tile = $this->wordIn($category, $user);

        $this->seed(AacVocabularySeeder::class);

        $this->assertModelExists($category);
        $this->assertModelExists($tile);
        $this->assertSame(13, Category::whereNull('user_id')->whereNull('parent_id')->count());
    }
}
