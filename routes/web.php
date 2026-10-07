<?php

use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Aac\ActivityController;
use App\Http\Controllers\Aac\CategoryController;
use App\Http\Controllers\Aac\SavedPhraseController;
use App\Http\Controllers\Aac\SettingsController;
use App\Http\Controllers\Aac\TileAudioController;
use App\Http\Controllers\Aac\TileController;
use App\Http\Controllers\Aac\UsageController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Therapist\ApprovalController;
use App\Http\Controllers\Therapist\RecordingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('aac.board'));

// Pending and rejected accounts can reach only this page (and log out).
Route::middleware('auth')->get('/account/status', [AccountStatusController::class, 'show'])->name('account.status');

Route::middleware(['auth', 'approved'])->group(function () {
    // Breeze sends people to "dashboard" after login; the board is our dashboard.
    Route::get('/dashboard', fn () => redirect()->route('aac.board'))->name('dashboard');

    Route::get('/board', [BoardController::class, 'show'])->name('aac.board');

    Route::prefix('aac')->name('aac.')->group(function () {
        Route::post('/tiles', [TileController::class, 'store'])->name('tiles.store');
        Route::put('/tiles/{tile}', [TileController::class, 'update'])->name('tiles.update');
        Route::delete('/tiles/{tile}', [TileController::class, 'destroy'])->name('tiles.destroy');
        Route::get('/tiles/{tile}/audio', [TileAudioController::class, 'show'])->name('tiles.audio');
        Route::post('/tiles/{tile}/audio', [TileAudioController::class, 'store'])->name('tiles.audio.store');
        Route::delete('/tiles/{tile}/audio', [TileAudioController::class, 'destroy'])->name('tiles.audio.destroy');

        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('/phrases', [SavedPhraseController::class, 'store'])->name('phrases.store');
        Route::delete('/phrases/{phrase}', [SavedPhraseController::class, 'destroy'])->name('phrases.destroy');

        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::post('/usage', [UsageController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('usage.store');

        Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
        Route::delete('/activity', [ActivityController::class, 'clear'])->name('activity.clear');
    });

    Route::middleware('can:therapist')->prefix('therapist')->name('therapist.')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
        Route::post('/approvals/{user}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{user}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
        Route::get('/recordings', [RecordingController::class, 'index'])->name('recordings');
    });

    // From Laravel Breeze. Kept behind approval so a rejected account can't delete itself and sign up again.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
