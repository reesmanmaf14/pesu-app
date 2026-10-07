<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'parent' or 'therapist'. Only the pesu:grant-therapist command makes a therapist.
            $table->string('role', 20)->default('parent')->after('password')->index();
            // 'pending', 'approved' or 'rejected'. New sign-ups wait for the therapist's approval.
            $table->string('status', 20)->default('pending')->after('role')->index();
            $table->timestamp('reviewed_at')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });

        // Everyone who already has an account keeps using the app.
        DB::table('users')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'status', 'reviewed_at']);
        });
    }
};
