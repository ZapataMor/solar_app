<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Designer3dTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_the_designer_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('solar-projects.index'))
            ->assertSee(route('designer.index'), false);

        $this->get(route('designer.index'))->assertOk()->assertSee('Diseñador 3D');
    }

    public function test_it_is_hidden_and_forbidden_outside_local_environment(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('solar-projects.index'))
            ->assertDontSee(route('designer.index'), false);

        $this->get(route('designer.index'))->assertForbidden();
    }

    public function test_regular_users_never_get_the_designer(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('designer.index'))
            ->assertForbidden();
    }
}
