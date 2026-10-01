<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_get_client_sections_without_administration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.index'))
            ->assertOk()
            ->assertSee('Mis proyectos')
            ->assertSee(route('solar-projects.create'), false)
            ->assertSee(route('guides.energy-bill'), false)
            ->assertSee(route('installers.index'), false)
            ->assertDontSee('Administración')
            ->assertDontSee(route('api-data.index'), false);
    }

    public function test_admins_also_get_the_administration_section(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('solar-projects.index'))
            ->assertOk()
            ->assertSee('Todos los proyectos')
            ->assertSee('Administración')
            ->assertSee(route('api-data.index'), false);
    }

    public function test_the_sidebar_has_a_visible_collapse_control(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.index'))
            ->assertOk()
            ->assertSee('data-test="sidebar-collapse-button"', false)
            ->assertSee('Recoger menú')
            ->assertSee('Expandir menú');
    }

    public function test_resource_pages_are_available_to_regular_users(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('guides.energy-bill'))
            ->assertOk()
            ->assertSee('Cómo leer tu recibo de energía');

        $this->get(route('installers.index'))
            ->assertOk()
            ->assertSee('Instaladores de tu zona');
    }
}
