<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AacVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shared top bar on every page except the board. */
class PesuNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = AacVocabularySeeder::class;

    public function test_parents_see_the_main_links_but_never_the_therapist_links(): void
    {
        $parent = User::factory()->create();

        foreach (['/aac/activity', '/profile'] as $url) {
            $this->actingAs($parent)->get($url)->assertOk()
                ->assertSee(route('aac.board'), false)
                ->assertSee(route('aac.activity'), false)
                ->assertSee(route('profile.edit'), false)
                ->assertSee('action="'.route('logout').'"', false)
                ->assertDontSee(route('therapist.recordings'), false)
                ->assertDontSee(route('therapist.approvals'), false);
        }
    }

    public function test_the_therapist_sees_the_therapist_links_on_every_page(): void
    {
        $therapist = User::factory()->therapist()->create();

        foreach (['/aac/activity', '/profile', '/therapist/approvals', '/therapist/recordings'] as $url) {
            $this->actingAs($therapist)->get($url)->assertOk()
                ->assertSee(route('therapist.recordings'), false)
                ->assertSee(route('therapist.approvals'), false);
        }
    }

    public function test_the_current_page_is_marked_in_the_navigation(): void
    {
        $this->actingAs(User::factory()->create())->get('/aac/activity')
            ->assertSeeInOrder(['href="'.route('aac.activity').'"', 'aria-current="page"'], false);
    }

    public function test_the_recordings_page_keeps_everything_its_script_needs(): void
    {
        $this->actingAs(User::factory()->therapist()->create())->get('/therapist/recordings')->assertOk()
            ->assertSee('id="recList"', false)
            ->assertSee('id="recCount"', false)
            ->assertSee('id="toast"', false)
            ->assertSee('id="toastMsg"', false)
            ->assertSee('id="rec-data"', false)
            ->assertSee('id="audDlg"', false)
            ->assertSee('id="i-sign-out"', false);
    }

    public function test_login_links_to_registration_and_register_links_back(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('register'), false)->assertSee('Create an account');
        $this->get('/register')->assertOk()->assertSee(route('login'), false);
    }

    public function test_the_layouts_use_the_pesu_name(): void
    {
        $this->get('/login')->assertSee('<title>Log in · Pesu</title>', false)->assertDontSee('Laravel');
        $this->actingAs(User::factory()->create())->get('/aac/activity')->assertSee('<title>Activity · Pesu</title>', false);
    }
}
