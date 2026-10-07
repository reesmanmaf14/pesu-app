<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Gives an existing, already-registered account therapist rights. Never creates an account. */
class GrantTherapist extends Command
{
    protected $signature = 'pesu:grant-therapist {email : Email of an account that has already registered}';

    protected $description = 'Make an existing account a therapist (approved), after confirmation';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();

        if (! $user) {
            $this->error('No account has that email. Register it at /register first. Nothing was changed.');

            return self::FAILURE;
        }

        $this->table(['ID', 'Name', 'Email', 'Registered', 'Role', 'Status'], [[
            $user->id, $user->name, $user->email, $user->created_at?->toDateTimeString(), $user->role, $user->status,
        ]]);

        if ($user->isTherapist() && $user->isApproved()) {
            $this->info('This account is already an approved therapist. Nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Make this account a therapist and approve it?', false)) {
            $this->warn('Cancelled. Nothing was changed.');

            return self::FAILURE;
        }

        $user->forceFill([
            'role' => User::ROLE_THERAPIST,
            'status' => User::STATUS_APPROVED,
            'reviewed_at' => now(),
        ])->save();

        Log::info('pesu:grant-therapist', ['user_id' => $user->id, 'email' => $user->email]);
        $this->info("{$user->email} is now a therapist.");

        return self::SUCCESS;
    }
}
