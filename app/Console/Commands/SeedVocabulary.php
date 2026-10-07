<?php

namespace App\Console\Commands;

use App\Models\Tile;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Console\Command;

/**
 * Loads the built-in vocabulary (AacVocabularySeeder only, never DatabaseSeeder and its test user).
 * With --if-empty it does nothing once built-in words exist, so a server can run it on every start.
 */
class SeedVocabulary extends Command
{
    protected $signature = 'pesu:vocabulary {--if-empty : Only load the words when there are no built-in words yet}';

    protected $description = 'Load or refresh the built-in vocabulary (keeps ids, recordings and users\' own words)';

    public function handle(): int
    {
        if ($this->option('if-empty') && Tile::whereNull('user_id')->exists()) {
            $this->info('Built-in words are already loaded. Nothing to do.');

            return self::SUCCESS;
        }

        return $this->call('db:seed', ['--class' => AacVocabularySeeder::class, '--force' => true]);
    }
}
