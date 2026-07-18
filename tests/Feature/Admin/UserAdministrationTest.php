<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_search_paginate_and_filter_users(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $area = Area::factory()->create();
        $role = $this->role('Mesa de Partes');
        User::factory()->count(11)->for($role)->create();
        $target = User::factory()->for($role)->create([
            'area_id' => $area->id,
            'document_number' => 'DOC-FILTRO-01',
            'first_name' => 'NombreFicticioBuscado',
            'last_name' => 'ApellidoFicticio',
            'email' => 'usuario.filtro@example.test',
            'active' => false,
        ]);

        $this->actingAs($administrator)->get(route('admin.users.index'))
            ->assertOk()->assertSee('Crear usuario interno')->assertSee('pagination');

        $this->get(route('admin.users.index', [
            'buscar' => 'NombreFicticioBuscado ApellidoFicticio',
            'rol' => $role->id,
            'area' => $area->id,
            'estado' => '0',
        ]))->assertOk()
            ->assertSee($target->email)
            ->assertDontSee(User::where('id', '!=', $target->id)->whereNotNull('email')->firstOrFail()->email);
    }

    public function test_administrator_can_view_user_detail(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $area = Area::factory()->create(['name' => 'Área ficticia interna']);
        $user = User::factory()->for($this->role('Responsable de área'))->create([
            'area_id' => $area->id,
            'first_name' => 'ResponsableFicticio',
        ]);

        $this->actingAs($administrator)->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('ResponsableFicticio')
            ->assertSee('Responsable de área')
            ->assertSee($area->name)
            ->assertSee('Restablecimiento de acceso');
    }

    public function test_administrator_creates_internal_user_without_exposing_a_password(): void
    {
        Notification::fake();
        $administrator = $this->userWithRole('Administrador');
        $role = $this->role('Mesa de Partes');

        $response = $this->actingAs($administrator)->post(route('admin.users.store'), [
            'role_id' => $role->id,
            'area_id' => '',
            'document_type' => 'DNI',
            'document_number' => 'INT-FICTICIO-01',
            'first_name' => 'Interno',
            'last_name' => 'Ficticio',
            'email' => 'interno.creado@example.test',
            'phone' => '',
        ]);

        $user = User::where('email', 'interno.creado@example.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertNotSame('', $user->password);
        $this->assertStringNotContainsString('INT-FICTICIO-01', $user->password);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access_reset_requested',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
        $changes = AuditLog::where('action', 'created')->whereMorphedTo('auditable', $user)->firstOrFail()->details['changes'];
        $this->assertArrayNotHasKey('password', $changes);
    }

    public function test_internal_creation_rejects_applicant_role_and_responsible_without_area(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $applicantRole = $this->role('Solicitante');
        $managerRole = $this->role('Responsable de área');

        $this->actingAs($administrator)->post(route('admin.users.store'), [
            ...$this->validUserData($applicantRole),
            'email' => 'solicitante.interno@example.test',
        ])->assertSessionHasErrors('role_id');

        $this->post(route('admin.users.store'), [
            ...$this->validUserData($managerRole),
            'email' => 'responsable.sin.area@example.test',
            'area_id' => null,
        ])->assertSessionHasErrors('area_id');
    }

    public function test_document_and_email_must_be_unique(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $role = $this->role('Mesa de Partes');
        $existing = User::factory()->for($role)->create();

        $this->actingAs($administrator)->post(route('admin.users.store'), [
            ...$this->validUserData($role),
            'document_number' => $existing->document_number,
            'email' => $existing->email,
        ])->assertSessionHasErrors(['document_number', 'email']);
    }

    public function test_administrator_edits_user_and_enforces_coherent_role_area_assignment(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $applicantRole = $this->role('Solicitante');
        $managerRole = $this->role('Responsable de área');
        $area = Area::factory()->create();
        $user = User::factory()->for($this->role('Mesa de Partes'))->create();

        $this->actingAs($administrator)->put(route('admin.users.update', $user), [
            ...$this->dataFromUser($user, $managerRole),
            'area_id' => null,
        ])->assertSessionHasErrors('area_id');

        $this->put(route('admin.users.update', $user), [
            ...$this->dataFromUser($user, $applicantRole),
            'area_id' => $area->id,
        ])->assertSessionHasErrors('area_id');

        $this->put(route('admin.users.update', $user), [
            ...$this->dataFromUser($user, $managerRole),
            'area_id' => $area->id,
            'first_name' => 'NombreActualizado',
        ])->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame($managerRole->id, $user->role_id);
        $this->assertSame($area->id, $user->area_id);
        $this->assertSame('NombreActualizado', $user->first_name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_administrator_can_deactivate_another_user_and_sessions_are_invalidated(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $user = User::factory()->for($this->role('Mesa de Partes'))->create(['active' => true]);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Prueba automatizada',
            'payload' => 'datos-ficticios',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)->patch(route('admin.users.toggle', $user))->assertRedirect();

        $this->assertFalse($user->fresh()->active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deactivated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_administrator_cannot_deactivate_itself_or_remove_its_own_role(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $otherRole = $this->role('Mesa de Partes');

        $this->actingAs($administrator)->patch(route('admin.users.toggle', $administrator))->assertForbidden();
        $this->assertTrue($administrator->fresh()->active);

        $this->put(route('admin.users.update', $administrator), $this->dataFromUser($administrator, $otherRole))
            ->assertSessionHasErrors('role_id');
        $this->assertSame('Administrador', $administrator->fresh()->role->name);
    }

    public function test_administrative_access_reset_sends_temporary_link_without_changing_password(): void
    {
        Notification::fake();
        $administrator = $this->userWithRole('Administrador');
        $user = User::factory()->for($this->role('Mesa de Partes'))->create(['active' => true]);
        $passwordBefore = $user->password;

        $this->actingAs($administrator)->post(route('admin.users.reset-access', $user))
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertSame($passwordBefore, $user->fresh()->password);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'access_reset_requested',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_inactive_user_cannot_receive_administrative_access_reset(): void
    {
        Notification::fake();
        $administrator = $this->userWithRole('Administrador');
        $user = User::factory()->for($this->role('Mesa de Partes'))->create(['active' => false]);

        $this->actingAs($administrator)->post(route('admin.users.reset-access', $user))->assertForbidden();
        Notification::assertNothingSent();
    }

    public function test_non_administrators_cannot_access_user_administration(): void
    {
        $target = User::factory()->for($this->role('Mesa de Partes'))->create();

        foreach (['Solicitante', 'Responsable de área'] as $roleName) {
            $user = $this->userWithRole($roleName);
            $this->actingAs($user);

            $this->get(route('admin.users.index'))->assertForbidden();
            $this->get(route('admin.users.show', $target))->assertForbidden();
            $this->post(route('admin.users.store'), [])->assertForbidden();
            $this->put(route('admin.users.update', $target), [])->assertForbidden();
            $this->patch(route('admin.users.toggle', $target))->assertForbidden();
            $this->post(route('admin.users.reset-access', $target))->assertForbidden();
            $this->assertFalse(Gate::forUser($user)->allows('viewAny', User::class));
        }
    }

    /** @return array<string, mixed> */
    private function validUserData(Role $role): array
    {
        return [
            'role_id' => $role->id,
            'area_id' => null,
            'document_type' => 'DNI',
            'document_number' => fake()->unique()->numerify('########'),
            'first_name' => 'NombreFicticio',
            'last_name' => 'ApellidoFicticio',
            'email' => fake()->unique()->userName().'@example.test',
            'phone' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function dataFromUser(User $user, Role $role): array
    {
        return [
            'role_id' => $role->id,
            'area_id' => $user->area_id,
            'document_type' => $user->document_type,
            'document_number' => $user->document_number,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], [
            'description' => "Rol ficticio {$name}",
            'active' => true,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->for($this->role($roleName))->create([
            'area_id' => $roleName === 'Responsable de área' ? Area::factory() : null,
            'active' => true,
        ]);
    }
}
