<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Undoes pesu:grant-therapist: the account becomes a parent again and stays approved. */
class RevokeTherapist extends Command
{
    protected $signature = 'pesu:revoke-therapist {email : Email of a therapist account}';

    protected $description = 'Turn a therapist account back into a parent account, after confirmation';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();

        if (! $user) {
            $this->error('No account has that email. Nothing was changed.');

            return self::FAILURE;
        }

        $this->table(['ID', 'Name', 'Email', 'Registered', 'Role', 'Status'], [[
            $user->id, $user->name, $user->email, $user->created_at?->toDateTimeString(), $user->role, $user->status,
        ]]);

        if (! $user->isTherapist()) {
            $this->info('This account is not a therapist. Nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Remove therapist rights from this account?', false)) {
            $this->warn('Cancelled. Nothing was changed.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => User::ROLE_PARENT])->save();

        Log::info('pesu:revoke-therapist', ['user_id' => $user->id, 'email' => $user->email]);
        $this->info("{$user->email} is now a parent account.");

        return self::SUCCESS;
    }
}
