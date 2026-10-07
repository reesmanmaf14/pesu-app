<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            // Recorded Tamil pronunciation on the private "local" disk, served by TileAudioController.
            $table->string('ta_audio_path')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropColumn('ta_audio_path');
        });
    }
};
