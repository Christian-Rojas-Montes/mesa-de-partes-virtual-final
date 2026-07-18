<?php

namespace Tests\Feature\Dashboard;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_page_is_accessible(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sistema Web de Mesa de Partes Virtual')
            ->assertSee('Crear cuenta de solicitante');
    }

    public function test_each_role_sees_only_its_initial_panel_options(): void
    {
        $cases = [
            [
                'role' => 'Solicitante',
                'route' => 'dashboard.applicant',
                'visible' => 'Registrar solicitud',
                'hidden' => 'Solicitudes recibidas',
                'has_upcoming' => false,
            ],
            [
                'role' => 'Mesa de Partes',
                'route' => 'dashboard.front-desk',
                'visible' => 'Solicitudes recibidas',
                'hidden' => 'Usuarios y roles',
                'has_upcoming' => false,
            ],
            [
                'role' => 'Responsable de área',
                'route' => 'dashboard.area-manager',
                'visible' => 'Expedientes asignados',
                'hidden' => 'Registrar solicitud',
                'has_upcoming' => false,
            ],
            [
                'role' => 'Administrador',
                'route' => 'dashboard.administrator',
                'visible' => 'Usuarios y roles',
                'hidden' => 'Emitir respuesta',
                'has_upcoming' => false,
            ],
        ];

        foreach ($cases as $case) {
            $role = Role::factory()->create(['name' => $case['role'], 'active' => true]);
            $user = User::factory()->for($role)->create(['active' => true]);

            $response = $this->actingAs($user)
                ->get(route($case['route']))
                ->assertOk()
                ->assertSee($case['visible'])
                ->assertDontSee($case['hidden']);

            $case['has_upcoming']
                ? $response->assertSee('Próximamente')
                : $response->assertDontSee('Próximamente');
        }
    }

    public function test_role_cannot_open_another_roles_panel(): void
    {
        $role = Role::factory()->create(['name' => 'Solicitante', 'active' => true]);
        $user = User::factory()->for($role)->create(['active' => true]);

        $this->actingAs($user)
            ->get(route('dashboard.front-desk'))
            ->assertForbidden()
            ->assertSee('Acceso denegado');
    }

    public function test_unknown_public_page_uses_the_custom_404_view(): void
    {
        $this->get('/pagina-publica-inexistente')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Volver al inicio');
    }
}
