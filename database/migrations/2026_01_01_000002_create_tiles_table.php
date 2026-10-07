php artisan db:seed --class=AacVocabularySeeder<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiles', function (Blueprint $table) {
            $table->id();
            // NULL user_id = built-in tile everyone sees. Otherwise a word that user added.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // NULL category_id + is_core = the always-visible core row.
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_core')->default(false);

            // Grammar kind: phrase, feel, noun, person, place, verb, adj, social, neg, frame
            $table->string('kind', 20);

            $table->string('emoji', 16)->nullable();
            $table->string('image_path')->nullable();

            $table->string('label_en');
            $table->string('label_ta');

            // Extra word forms so sentences come out grammatical in Tamil and English
            $table->string('ta_dative')->nullable();      // பூங்கா → பூங்காவுக்கு ("to the park")
            $table->string('ta_infinitive')->nullable();  // விளையாடு → விளையாட (after "I want")
            $table->string('en_to')->nullable();          // "to the park", "home"
            $table->string('en_infinitive')->nullable();  // "to have a bath"
            $table->boolean('en_article')->default(false); // say "the park" rather than "park"

            // Sentence frames: "I want {}" / "எனக்கு {} வேண்டும்"
            $table->string('frame_key', 30)->nullable();
            $table->string('template_en')->nullable();
            $table->string('template_ta')->nullable();
            $table->json('accepts')->nullable(); // kinds that can fill the {} slot

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiles');
    }
};
