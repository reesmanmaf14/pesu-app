<?php

namespace Tests\Feature;

use App\Models\Tile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedVocabularyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_built_in_words_into_an_empty_database_without_creating_users(): void
    {
        $this->artisan('pesu:vocabulary', ['--if-empty' => true])->assertSuccessful();

        $this->assertGreaterThan(200, Tile::whereNull('user_id')->count());
        $this->assertSame(0, User::count());
    }

    public function test_with_if_empty_it_leaves_existing_words_alone(): void
    {
        $this->artisan('pesu:vocabulary')->assertSuccessful();
        $tile = Tile::whereNull('user_id')->first();
        $tile->forceFill(['ta_audio_path' => 'aac/audio/shared/x.webm'])->save();
        $before = Tile::orderBy('id')->get()->map->getAttributes()->all();

        $this->travel(5)->minutes();
        $this->artisan('pesu:vocabulary', ['--if-empty' => true])
            ->expectsOutput('Built-in words are already loaded. Nothing to do.')
            ->assertSuccessful();

        $this->assertSame($before, Tile::orderBy('id')->get()->map->getAttributes()->all());
    }
}
