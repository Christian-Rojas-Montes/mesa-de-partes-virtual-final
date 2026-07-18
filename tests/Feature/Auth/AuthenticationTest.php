<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_register_without_choosing_a_role(): void
    {
        $applicantRole = $this->createRole('Solicitante');

        $response = $this->post(route('register'), $this->registrationData());

        $response->assertRedirect(route('dashboard.applicant'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'nueva.cuenta@example.test')->firstOrFail();
        $this->assertSame($applicantRole->id, $user->role_id);
        $this->assertNull($user->area_id);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('ClaveDemo9!', $user->password));
    }

    public function test_registration_rejects_a_duplicate_document_number(): void
    {
        $role = $this->createRole('Solicitante');
        User::factory()->for($role)->create(['document_number' => 'DOC-DEMO-001']);

        $response = $this->from(route('register'))->post(route('register'), $this->registrationData([
            'document_number' => 'DOC-DEMO-001',
        ]));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('document_number');
        $this->assertGuest();
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        $role = $this->createRole('Solicitante');
        User::factory()->for($role)->create(['email' => 'nueva.cuenta@example.test']);

        $response = $this->from(route('register'))->post(route('register'), $this->registrationData());

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_user_can_log_in_and_is_redirected_by_role(): void
    {
        $role = $this->createRole('Mesa de Partes');
        $user = User::factory()->for($role)->create([
            'email' => 'mesa.login@example.test',
            'password' => 'ClaveDemo9!',
            'active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'ClaveDemo9!',
        ]);

        $response->assertRedirect(route('dashboard.front-desk'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_each_role_is_redirected_to_its_own_dashboard(): void
    {
        $cases = [
            'Solicitante' => 'dashboard.applicant',
            'Mesa de Partes' => 'dashboard.front-desk',
            'Responsable de área' => 'dashboard.area-manager',
            'Administrador' => 'dashboard.administrator',
        ];

        foreach ($cases as $roleName => $routeName) {
            $role = $this->createRole($roleName);
            $user = User::factory()->for($role)->create([
                'email' => str($routeName)->replace('.', '-').'@example.test',
                'password' => 'ClaveDemo9!',
            ]);

            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'ClaveDemo9!',
            ])->assertRedirect(route($routeName));

            $this->post(route('logout'));
        }
    }

    public function test_login_rejects_incorrect_credentials(): void
    {
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create(['password' => 'ClaveDemo9!']);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'ClaveIncorrecta9!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create([
            'password' => 'ClaveDemo9!',
            'active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'ClaveDemo9!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_with_inactive_role_cannot_log_in(): void
    {
        $role = Role::factory()->create(['name' => 'Solicitante', 'active' => false]);
        $user = User::factory()->for($role)->create([
            'password' => 'ClaveDemo9!',
            'active' => true,
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'ClaveDemo9!',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_existing_session_is_closed_when_role_becomes_inactive(): void
    {
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create(['active' => true]);
        $role->update(['active' => false]);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_cannot_access_a_dashboard_for_another_role(): void
    {
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create();

        $this->actingAs($user)
            ->get(route('dashboard.administrator'))
            ->assertForbidden()
            ->assertSee('Acceso denegado');
    }

    public function test_password_recovery_sends_a_notification_without_revealing_account_existence(): void
    {
        Notification::fake();
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create([
            'email' => 'recuperacion@example.test',
            'active' => true,
        ]);

        $existingResponse = $this->post(route('password.email'), ['email' => $user->email]);
        $missingResponse = $this->post(route('password.email'), ['email' => 'inexistente@example.test']);

        $genericStatus = 'Si el correo corresponde a una cuenta activa, recibirás un enlace para restablecer la contraseña.';

        $existingResponse->assertSessionHas('status', $genericStatus);
        $missingResponse->assertSessionHas('status', $genericStatus);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_recovery_is_rate_limited_after_five_requests(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), [
                'email' => "inexistente-{$attempt}@example.test",
            ])->assertRedirect();
        }

        $this->post(route('password.email'), [
            'email' => 'inexistente-final@example.test',
        ])->assertTooManyRequests();
    }

    public function test_user_can_reset_password_with_a_valid_temporary_token(): void
    {
        $role = $this->createRole('Solicitante');
        $user = User::factory()->for($role)->create(['password' => 'ClaveAnterior9!']);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ClaveNueva9!',
            'password_confirmation' => 'ClaveNueva9!',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('ClaveNueva9!', $user->fresh()->password));
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $credentials = [
            'email' => 'limite@example.test',
            'password' => 'ClaveIncorrecta9!',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), $credentials)->assertSessionHasErrors('email');
        }

        $response = $this->post(route('login'), $credentials);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Demasiados intentos',
            session('errors')->first('email'),
        );
    }

    private function createRole(string $name): Role
    {
        return Role::factory()->create([
            'name' => $name,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function registrationData(array $overrides = []): array
    {
        return array_replace([
            'document_type' => 'OTRO',
            'document_number' => 'DOC-NUEVO-001',
            'first_name' => 'Cuenta',
            'last_name' => 'Ficticia',
            'email' => 'nueva.cuenta@example.test',
            'phone' => null,
            'password' => 'ClaveDemo9!',
            'password_confirmation' => 'ClaveDemo9!',
        ], $overrides);
    }
}
