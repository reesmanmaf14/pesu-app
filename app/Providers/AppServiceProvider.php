<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The therapist pages (approvals, recordings). TilePolicy and UserPolicy are found automatically.
        Gate::define('therapist', fn (User $user) => $user->isTherapist() && $user->isApproved());
    }
}
