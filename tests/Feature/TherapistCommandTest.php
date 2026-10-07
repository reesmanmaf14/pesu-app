<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_makes_an_existing_account_an_approved_therapist_after_confirmation(): void
    {
        $user = User::factory()->pending()->create(['email' => 'sister@example.com']);

        $this->artisan('pesu:grant-therapist', ['email' => 'Sister@Example.com'])
            ->expectsConfirmation('Make this account a therapist and approve it?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isTherapist());
        $this->assertTrue($user->isApproved());
    }

    public function test_grant_changes_nothing_without_confirmation(): void
    {
        $user = User::factory()->pending()->create(['email' => 'sister@example.com']);

        $this->artisan('pesu:grant-therapist', ['email' => 'sister@example.com'])
            ->expectsConfirmation('Make this account a therapist and approve it?', 'no')
            ->assertFailed();

        $user->refresh();
        $this->assertFalse($user->isTherapist());
        $this->assertTrue($user->isPending());
    }

    public function test_grant_fails_for_an_unknown_email_and_creates_no_account(): void
    {
        $this->artisan('pesu:grant-therapist', ['email' => 'nobody@example.com'])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_grant_twice_changes_nothing_the_second_time(): void
    {
        $user = User::factory()->therapist()->create(['email' => 'sister@example.com']);

        $this->artisan('pesu:grant-therapist', ['email' => 'sister@example.com'])->assertSuccessful();

        $this->assertTrue($user->fresh()->isTherapist());
    }

    public function test_revoke_turns_a_therapist_back_into_an_approved_parent(): void
    {
        $user = User::factory()->therapist()->create(['email' => 'sister@example.com']);

        $this->artisan('pesu:revoke-therapist', ['email' => 'sister@example.com'])
            ->expectsConfirmation('Remove therapist rights from this account?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->isTherapist());
        $this->assertTrue($user->isApproved());
    }
}
