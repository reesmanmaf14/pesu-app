<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/register', [
            'name' => 'A Parent',
            'email' => 'parent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$extra,
        ]);
    }

    public function test_registration_creates_a_pending_parent(): void
    {
        $this->register()->assertRedirect('/account/status');

        $user = User::where('email', 'parent@example.com')->first();
        $this->assertSame(User::ROLE_PARENT, $user->role);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->get('/account/status')->assertOk()->assertSee('Waiting for approval');
    }

    public function test_role_and_status_sent_with_the_registration_form_are_ignored(): void
    {
        $this->register(['role' => 'therapist', 'status' => 'approved']);

        $user = User::where('email', 'parent@example.com')->first();
        $this->assertSame(User::ROLE_PARENT, $user->role);
        $this->assertSame(User::STATUS_PENDING, $user->status);
    }

    public function test_pending_users_are_kept_out_of_the_board_and_aac_routes(): void
    {
        $user = User::factory()->pending()->create();

        foreach (['/board', '/dashboard', '/aac/activity', '/profile'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect('/account/status');
        }
        $this->actingAs($user)->postJson('/aac/phrases', ['text' => 'hello'])->assertForbidden();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
    }

    public function test_rejected_users_are_kept_out_and_cannot_delete_their_account(): void
    {
        $user = User::factory()->rejected()->create();

        $this->actingAs($user)->get('/board')->assertRedirect('/account/status');
        $this->actingAs($user)->get('/account/status')->assertOk()->assertSee('not approved');
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/account/status');
        $this->assertNotNull($user->fresh());
    }

    public function test_approved_users_skip_the_status_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/account/status')->assertRedirect('/board');
    }

    public function test_the_therapist_can_approve_a_pending_parent(): void
    {
        $therapist = User::factory()->therapist()->create();
        $parent = User::factory()->pending()->create();

        $this->actingAs($therapist)->get('/therapist/approvals')->assertOk()->assertSee($parent->email);
        $this->actingAs($therapist)->post("/therapist/approvals/{$parent->id}/approve")->assertRedirect('/therapist/approvals');

        $parent->refresh();
        $this->assertTrue($parent->isApproved());
        $this->assertSame($therapist->id, $parent->reviewed_by);
        $this->assertNotNull($parent->reviewed_at);
        $this->actingAs($parent)->get('/board')->assertOk();
    }

    public function test_the_therapist_can_reject_and_later_reconsider(): void
    {
        $therapist = User::factory()->therapist()->create();
        $parent = User::factory()->pending()->create();

        $this->actingAs($therapist)->post("/therapist/approvals/{$parent->id}/reject");
        $this->assertTrue($parent->fresh()->isRejected());
        $this->assertDatabaseHas('users', ['id' => $parent->id]);

        $this->actingAs($therapist)->post("/therapist/approvals/{$parent->id}/approve");
        $this->assertTrue($parent->fresh()->isApproved());
    }

    public function test_a_status_change_applies_to_a_session_that_is_already_open(): void
    {
        $therapist = User::factory()->therapist()->create();
        $parent = User::factory()->create();

        $this->actingAs($parent)->get('/board')->assertOk();
        $this->actingAs($therapist)->post("/therapist/approvals/{$parent->id}/reject");
        $this->actingAs($parent->fresh())->get('/board')->assertRedirect('/account/status');
    }

    public function test_a_rejected_email_cannot_register_again(): void
    {
        User::factory()->rejected()->create(['email' => 'parent@example.com']);

        $this->register()->assertSessionHasErrors(['email' => 'The email has already been taken.']);
        $this->assertSame(1, User::where('email', 'parent@example.com')->count());
    }

    public function test_parents_cannot_see_or_use_the_approvals_page(): void
    {
        $parent = User::factory()->create();
        $other = User::factory()->pending()->create();

        $this->actingAs($parent)->get('/therapist/approvals')->assertForbidden();
        $this->actingAs($parent)->post("/therapist/approvals/{$other->id}/approve")->assertForbidden();
        $this->assertTrue($other->fresh()->isPending());
    }

    public function test_a_therapist_cannot_review_herself_or_another_therapist(): void
    {
        $therapist = User::factory()->therapist()->create();
        $colleague = User::factory()->therapist()->create();

        $this->actingAs($therapist)->post("/therapist/approvals/{$therapist->id}/reject")->assertForbidden();
        $this->actingAs($therapist)->post("/therapist/approvals/{$colleague->id}/reject")->assertForbidden();
        $this->assertTrue($therapist->fresh()->isApproved());
        $this->assertTrue($colleague->fresh()->isApproved());
    }

    public function test_only_the_therapist_sees_the_therapist_links_on_the_board(): void
    {
        $this->actingAs(User::factory()->create())->get('/board')
            ->assertDontSee('/therapist/approvals')
            ->assertViewHas('payload', fn (array $p) => $p['canRecordShared'] === false);

        $this->actingAs(User::factory()->therapist()->create())->get('/board')
            ->assertSee('/therapist/approvals')
            ->assertViewHas('payload', fn (array $p) => $p['canRecordShared'] === true);
    }
}
