<?php

namespace Tests\Feature\Notifications;

use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Notifications\InternalProcedureRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuredProcedureNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_targets_owner_and_foreign_user_cannot_manage_or_read_it(): void
    {
        [$staff, $owner, $other, $request] = $this->context();
        $this->actingAs($staff)->post(route('communications.appointments.store', $request), $this->appointment())->assertRedirect();
        $this->assertDatabaseHas('request_appointments', ['procedure_request_id' => $request->id, 'status' => 'scheduled']);
        $this->assertSame(1, $owner->notifications()->where('data->event', 'appointment_scheduled')->count());
        $this->assertSame(0, $other->notifications()->count());
        $appointment = $request->appointments()->firstOrFail();
        $this->patch(route('communications.appointments.status', [$request, $appointment]), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->actingAs($other)->get(route('communications.show', $request))->assertForbidden();
        $this->get(route('notifications.index'))->assertOk()->assertDontSee('Oficina ficticia');
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Próxima cita')->assertSee('Oficina ficticia');
    }

    public function test_appointment_can_be_rescheduled_with_history_and_audit(): void
    {
        [$staff, $owner, , $request] = $this->context();
        $this->actingAs($staff)->post(route('communications.appointments.store', $request), $this->appointment())->assertRedirect();
        $appointment = $request->appointments()->firstOrFail();
        $newData = $this->appointment(['appointment_date' => now()->addDays(3)->toDateString(), 'starts_at' => '11:00', 'ends_at' => '12:00']);
        $this->post(route('communications.appointments.reschedule', [$request, $appointment]), $newData)->assertRedirect();
        $this->assertSame('rescheduled', $appointment->fresh()->status);
        $this->assertDatabaseHas('request_appointments', ['rescheduled_from_id' => $appointment->id, 'starts_at' => '11:00']);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'appointment_rescheduled']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'appointment_rescheduled']);
        $this->assertSame(1, $owner->notifications()->where('data->event', 'appointment_rescheduled')->count());
    }

    public function test_pickup_and_delivery_are_structured_without_identity_copy(): void
    {
        [$staff, $owner, , $request] = $this->context();
        $this->actingAs($staff)->post(route('communications.pickup.ready', $request), ['available_at' => now()->toDateTimeString(), 'office' => 'Ventanilla ficticia', 'pickup_requirement' => 'Presentar documento vigente'])->assertRedirect();
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Documento listo para recoger')->assertSee('Ventanilla ficticia');
        $this->actingAs($staff)->post(route('communications.pickup.deliver', $request), ['received_by_name' => 'Persona receptora ficticia', 'identity_document_verified' => '1', 'delivered_at' => now()->toDateTimeString(), 'observation' => 'Entrega conforme.'])->assertRedirect();
        $this->assertDatabaseHas('request_pickups', ['procedure_request_id' => $request->id, 'status' => 'delivered', 'identity_document_verified' => true, 'received_by_name' => 'Persona receptora ficticia']);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'delivered']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'document_delivered']);
        $this->assertSame(1, $owner->notifications()->where('data->event', 'document_delivered')->count());
    }

    public function test_email_disabled_uses_internal_channel_and_sensitive_terms_are_removed(): void
    {
        config(['internal-notifications.email_enabled' => false]);
        [$staff, $owner, , $request] = $this->context();
        $notification = new InternalProcedureRequestNotification($request, 'request_observed', 'Mensaje', 'test');
        $this->assertSame(['database'], $notification->via($owner));
        $this->actingAs($staff)->post(route('communications.notify', $request), ['type' => 'request_observed', 'message' => 'Adjuntar historia clínica, diagnóstico y sentencia.'])->assertRedirect();
        $stored = $owner->notifications()->where('data->event', 'request_observed')->firstOrFail();
        $this->assertStringNotContainsString('historia clínica', mb_strtolower($stored->data['message']));
        $this->assertStringNotContainsString('diagnóstico', mb_strtolower($stored->data['message']));
        $this->assertStringNotContainsString('sentencia', mb_strtolower($stored->data['message']));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'structured_notification_sent']);
    }

    private function context(): array
    {
        $staff = User::factory()->for($this->role('Mesa de Partes'))->create(['active' => true]);
        $owner = User::factory()->for($this->role('Solicitante'))->create(['active' => true]);
        $other = User::factory()->for($this->role('Solicitante'))->create(['active' => true]);
        $status = Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado', 'active' => true]);
        $request = ProcedureRequest::factory()->for($owner)->for(ProcedureType::factory()->create())->for($status)->create();

        return [$staff, $owner, $other, $request];
    }

    private function appointment(array $overrides = []): array
    {
        return ['appointment_date' => now()->addDays(2)->toDateString(), 'starts_at' => '09:00', 'ends_at' => '10:00', 'office' => 'Oficina ficticia', 'reason' => 'Verificación ficticia', 'instructions' => 'Presentarse con anticipación.', ...$overrides];
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], ['description' => 'Rol ficticio', 'active' => true]);
    }
}
