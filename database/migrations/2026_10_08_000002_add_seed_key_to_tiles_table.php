<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work out every key first, so nothing is written if two built-in words would clash.
        // Same format as AacVocabularySeeder::seedKey(): "category-slug|English label|Tamil label".
        $keys = DB::table('tiles')
            ->join('categories', 'categories.id', '=', 'tiles.category_id')
            ->whereNull('tiles.user_id')
            ->orderBy('tiles.id')
            ->get(['tiles.id', 'categories.slug', 'tiles.label_en', 'tiles.label_ta'])
            ->mapWithKeys(fn ($t) => [$t->id => "{$t->slug}|{$t->label_en}|{$t->label_ta}"]);

        $duplicates = $keys->duplicates()->unique();
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Built-in words share a seed key, nothing was changed: '.$duplicates->implode(', '));
        }

        Schema::table('tiles', function (Blueprint $table) {
            // Stable name for a built-in word, so the seeder updates it in place (keeping its id and
            // recording) instead of re-creating it. NULL for words users added.
            $table->string('seed_key')->nullable()->after('user_id')->unique();
        });

        // Query builder, not Eloquent: only seed_key is written; updated_at and everything else stay as they are.
        foreach ($keys as $id => $key) {
            DB::table('tiles')->where('id', $id)->update(['seed_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropUnique(['seed_key']);
            $table->dropColumn('seed_key');
        });
    }
};
