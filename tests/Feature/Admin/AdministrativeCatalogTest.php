<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdministrativeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_search_and_paginate_areas(): void
    {
        $administrator = $this->userWithRole('Administrador');
        Area::factory()->count(11)->create();
        $target = Area::factory()->create(['code' => 'BUS-01', 'name' => 'Área ficticia buscada']);

        $this->actingAs($administrator)->get(route('admin.areas.index'))
            ->assertOk()
            ->assertSee('Crear área')
            ->assertSee('pagination');

        $this->get(route('admin.areas.index', ['buscar' => 'BUS-01']))
            ->assertOk()
            ->assertSee($target->name)
            ->assertDontSee(Area::whereKeyNot($target->id)->firstOrFail()->name);
    }

    public function test_administrator_can_create_update_and_deactivate_an_area_with_audit_logs(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $this->actingAs($administrator)->post(route('admin.areas.store'), [
            'code' => ' ar-gestion ',
            'name' => 'Área ficticia de Gestión',
            'description' => 'Área utilizada únicamente para pruebas.',
        ])->assertRedirect(route('admin.areas.index'));

        $area = Area::where('code', 'AR-GESTION')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'created',
            'auditable_type' => Area::class,
            'auditable_id' => $area->id,
        ]);

        $this->put(route('admin.areas.update', $area), [
            'code' => $area->code,
            'name' => 'Área ficticia actualizada',
            'description' => 'Descripción ficticia actualizada.',
        ])->assertRedirect(route('admin.areas.index'));

        $this->patch(route('admin.areas.toggle', $area))->assertRedirect();
        $this->assertFalse($area->fresh()->active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deactivated',
            'auditable_type' => Area::class,
            'auditable_id' => $area->id,
        ]);
        $this->assertSame(3, AuditLog::whereMorphedTo('auditable', $area)->count());
    }

    public function test_area_and_procedure_type_unique_values_are_validated(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $area = Area::factory()->create();
        $procedureType = ProcedureType::factory()->create();

        $this->actingAs($administrator)->post(route('admin.areas.store'), [
            'code' => $area->code,
            'name' => $area->name,
            'description' => 'Descripción ficticia.',
        ])->assertSessionHasErrors(['code', 'name']);

        $this->post(route('admin.procedure-types.store'), [
            'code' => $procedureType->code,
            'name' => $procedureType->name,
            'description' => 'Descripción ficticia.',
            'attention_days' => 10,
        ])->assertSessionHasErrors(['code', 'name']);
    }

    public function test_administrator_manages_procedure_types_and_active_scope_excludes_inactive_records(): void
    {
        $administrator = $this->userWithRole('Administrador');

        $this->actingAs($administrator)->post(route('admin.procedure-types.store'), [
            'code' => 'TR-FICTICIO',
            'name' => 'Trámite ficticio administrativo',
            'description' => 'Trámite creado para una prueba automatizada.',
            'attention_days' => 15,
        ])->assertRedirect(route('admin.procedure-types.index'));

        $procedureType = ProcedureType::where('code', 'TR-FICTICIO')->firstOrFail();
        $this->put(route('admin.procedure-types.update', $procedureType), [
            'code' => $procedureType->code,
            'name' => 'Trámite ficticio actualizado',
            'description' => $procedureType->description,
            'attention_days' => 20,
        ])->assertRedirect(route('admin.procedure-types.index'));

        $this->patch(route('admin.procedure-types.toggle', $procedureType))->assertRedirect();

        $this->assertFalse($procedureType->fresh()->active);
        $this->assertFalse(ProcedureType::active()->whereKey($procedureType)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deactivated',
            'auditable_type' => ProcedureType::class,
            'auditable_id' => $procedureType->id,
        ]);
    }

    public function test_administrator_manages_requirements_inside_their_procedure_type(): void
    {
        $procedureType = ProcedureType::factory()->create();
        $administrator = $this->userWithRole('Administrador');

        $this->actingAs($administrator)->post(route('admin.procedure-types.requirements.store', $procedureType), [
            'name' => 'Documento ficticio de sustento',
            'description' => 'Metadato requerido únicamente para pruebas.',
            'required' => '0',
        ])->assertRedirect(route('admin.procedure-types.requirements.index', $procedureType));

        $requirement = $procedureType->requirements()->firstOrFail();
        $this->get(route('admin.procedure-types.requirements.index', $procedureType))
            ->assertOk()->assertSee($requirement->name)->assertSee('Opcional');

        $this->put(route('admin.procedure-types.requirements.update', [$procedureType, $requirement]), [
            'name' => 'Documento ficticio actualizado',
            'description' => $requirement->description,
            'required' => '1',
        ])->assertRedirect(route('admin.procedure-types.requirements.index', $procedureType));

        $this->patch(route('admin.procedure-types.requirements.toggle', [$procedureType, $requirement]))
            ->assertRedirect();

        $this->assertTrue($requirement->fresh()->required);
        $this->assertFalse($requirement->fresh()->active);
        $this->assertFalse($procedureType->activeRequirements()->whereKey($requirement)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deactivated',
            'auditable_type' => ProcedureRequirement::class,
            'auditable_id' => $requirement->id,
        ]);
    }

    public function test_requirement_name_is_unique_within_its_procedure_type(): void
    {
        $procedureType = ProcedureType::factory()->create();
        $requirement = ProcedureRequirement::factory()->for($procedureType)->create([
            'name' => 'Requisito ficticio único',
        ]);
        $administrator = $this->userWithRole('Administrador');

        $this->actingAs($administrator)->post(route('admin.procedure-types.requirements.store', $procedureType), [
            'name' => $requirement->name,
            'description' => 'Descripción ficticia duplicada.',
            'required' => '1',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, $procedureType->requirements()->count());
    }

    public function test_statuses_are_available_to_administrator_in_read_only_mode(): void
    {
        $status = Status::factory()->create(['name' => 'Estado ficticio consultable']);
        $administrator = $this->userWithRole('Administrador');

        $this->actingAs($administrator)->get(route('admin.statuses.index'))
            ->assertOk()
            ->assertSee($status->name)
            ->assertSee('solo lectura')
            ->assertDontSee('Crear estado');

        $this->post('/panel/administracion/catalogos/estados', [])->assertMethodNotAllowed();
    }

    public function test_non_administrators_cannot_access_catalog_routes_or_mutate_records(): void
    {
        $area = Area::factory()->create();
        $procedureType = ProcedureType::factory()->create();
        $requirement = ProcedureRequirement::factory()->for($procedureType)->create();
        Status::factory()->create();

        foreach (['Solicitante', 'Mesa de Partes', 'Responsable de área'] as $roleName) {
            $user = $this->userWithRole($roleName);
            $this->actingAs($user);

            $this->get(route('admin.areas.index'))->assertForbidden();
            $this->post(route('admin.areas.store'), [])->assertForbidden();
            $this->patch(route('admin.areas.toggle', $area))->assertForbidden();
            $this->get(route('admin.procedure-types.index'))->assertForbidden();
            $this->patch(route('admin.procedure-types.toggle', $procedureType))->assertForbidden();
            $this->get(route('admin.procedure-types.requirements.index', $procedureType))->assertForbidden();
            $this->patch(route('admin.procedure-types.requirements.toggle', [$procedureType, $requirement]))->assertForbidden();
            $this->get(route('admin.statuses.index'))->assertForbidden();
        }
    }

    public function test_catalog_policies_allow_only_administrators(): void
    {
        $area = Area::factory()->create();
        $administrator = $this->userWithRole('Administrador');
        $applicant = $this->userWithRole('Solicitante');

        $this->assertTrue(Gate::forUser($administrator)->allows('update', $area));
        $this->assertTrue(Gate::forUser($administrator)->allows('viewAny', Status::class));
        $this->assertFalse(Gate::forUser($applicant)->allows('update', $area));
        $this->assertFalse(Gate::forUser($applicant)->allows('create', ProcedureType::class));
        $this->assertFalse(Gate::forUser($applicant)->allows('create', ProcedureRequirement::class));

        $this->actingAs($administrator)->get(route('dashboard.administrator'))
            ->assertOk()
            ->assertSee(route('admin.areas.index'), false)
            ->assertSee(route('admin.procedure-types.index'), false)
            ->assertSee(route('admin.statuses.index'), false);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::factory()->create(['name' => $roleName, 'active' => true]);

        return User::factory()->for($role)->create(['active' => true]);
    }
}
