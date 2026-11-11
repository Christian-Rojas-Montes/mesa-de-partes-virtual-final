<?php

namespace Tests\Feature\Profile;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_view_its_own_profile(): void
    {
        foreach (['Solicitante', 'Mesa de Partes', 'Responsable de área', 'Administrador'] as $index => $roleName) {
            $user = $this->userWithRole($roleName);
            $documentNumber = sprintf('DOC-PRIVADO-%02d1234', $index);
            $user->forceFill([
                'document_number' => $documentNumber,
                'academic_program' => 'Programa ficticio',
            ])->save();
            $maskedDocument = str_repeat('*', max(0, mb_strlen($documentNumber) - 4)).mb_substr($documentNumber, -4);

            $this->actingAs($user)->get(route('profile.show'))
                ->assertOk()
                ->assertSee('Mi perfil')
                ->assertSee($maskedDocument)
                ->assertSee('Programa ficticio')
                ->assertDontSee($documentNumber)
                ->assertSee('Guardar teléfono');
        }
    }

    public function test_profile_requires_an_active_authenticated_account(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));

        $user = $this->userWithRole('Solicitante');
        $user->update(['active' => false]);

        $this->actingAs($user)->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_user_can_update_only_their_phone_and_change_is_audited(): void
    {
        $user = $this->userWithRole('Solicitante');
        $otherRole = $this->role('Administrador');
        $other = User::factory()->for($otherRole)->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'phone' => '  999123456  ',
            'email' => 'changed@example.test',
            'document_number' => 'DOC-ALTERADO-99',
            'role_id' => $otherRole->id,
            'area_id' => Area::factory()->create()->id,
            'first_name' => 'NombreAlterado',
        ])->assertRedirect(route('profile.show'))->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('999123456', $user->phone);
        $this->assertNotSame('changed@example.test', $user->email);
        $this->assertNotSame('DOC-ALTERADO-99', $user->document_number);
        $this->assertNotSame($otherRole->id, $user->role_id);
        $this->assertNotSame('NombreAlterado', $user->first_name);
        $this->assertSame($otherRole->id, $other->fresh()->role_id);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile_phone_updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_password_change_requires_current_password_and_invalidates_other_sessions(): void
    {
        $user = $this->userWithRole('Solicitante');
        $user->forceFill(['password' => 'ClaveActual9!'])->save();
        $oldRememberToken = $user->remember_token;
        $otherSessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $otherSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Prueba automatizada',
            'payload' => 'datos-ficticios',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'ClaveIncorrecta9!',
            'password' => 'ClaveNueva9!',
            'password_confirmation' => 'ClaveNueva9!',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('profile.password.update'), [
            'current_password' => 'ClaveActual9!',
            'password' => 'ClaveNueva9!',
            'password_confirmation' => 'ClaveNueva9!',
        ])->assertRedirect(route('profile.show'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('ClaveNueva9!', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile_password_changed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
        $details = AuditLog::query()->where('action', 'profile_password_changed')->firstOrFail()->details;
        $this->assertSame(['field' => 'password'], $details);
        $this->assertStringNotContainsString('ClaveNueva9!', json_encode($details));
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->for($this->role($roleName))->create(['active' => true]);
    }

    private function role(string $name): Role
    {
        return Role::query()->firstOrCreate(['name' => $name], [
            'description' => 'Rol ficticio',
            'active' => true,
        ]);
    }
}
