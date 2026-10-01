<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_with_sign_up_calls_to_action(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Crea tu cuenta y empieza tu proyecto')
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
    }

    public function test_authenticated_users_go_straight_to_their_projects(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('solar-projects.index'));
    }
}
