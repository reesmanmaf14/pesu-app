<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home page sends people straight to the AAC board.
     */
    public function test_the_home_page_redirects_to_the_board(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('aac.board'));
    }
}
