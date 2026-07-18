<?php

namespace Tests\Feature\FrontDesk;

use App\Enums\PresentationModeCode;
use App\Models\PresentationModality;
use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhysicalProcedureRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado', 'active' => true]);
    }

    public function test_existing_applicant_is_linked_without_changing_personal_data(): void
    {
        [$staff, $type, $requirement] = $this->context();
        $applicant = User::factory()->for($this->role('Solicitante'))->create(['first_name' => 'Nombre original', 'document_number' => 'DOC-100']);
        $this->actingAs($staff)->post(route('front-desk.physical-registration.store'), $this->payload($type, $requirement, ['existing_user_id' => $applicant->id, 'confirm_existing_identity' => '1', 'first_name' => 'No modificar']))->assertRedirect();
        $this->assertSame($applicant->id, ProcedureRequest::firstOrFail()->user_id);
        $this->assertSame('Nombre original', $applicant->fresh()->first_name);
    }

    public function test_new_applicant_without_email_is_created_pending_claim_without_document_password(): void
    {
        [$staff, $type, $requirement] = $this->context();
        $this->actingAs($staff)->post(route('front-desk.physical-registration.store'), $this->payload($type, $requirement, ['document_type' => 'DNI', 'document_number' => '99887766', 'first_name' => 'Persona', 'last_name' => 'Ficticia', 'academic_program' => 'Programa ficticio', 'academic_condition' => 'Egresado', 'email' => null]))->assertRedirect();
        $applicant = User::where('document_number', '99887766')->firstOrFail();
        $this->assertTrue($applicant->account_claim_pending);
        $this->assertFalse($applicant->active);
        $this->assertNull($applicant->email);
        $this->assertFalse(password_verify('99887766', $applicant->password));
    }

    public function test_duplicate_document_cannot_create_another_account(): void
    {
        [$staff, $type, $requirement] = $this->context();
        User::factory()->for($this->role('Solicitante'))->create(['document_number' => 'DUP-01']);
        $this->actingAs($staff)->post(route('front-desk.physical-registration.store'), $this->payload($type, $requirement, ['document_type' => 'DNI', 'document_number' => 'DUP-01', 'first_name' => 'Otra', 'last_name' => 'Persona', 'academic_program' => 'Programa', 'academic_condition' => 'Regular']))->assertSessionHasErrors('document_number');
        $this->assertDatabaseCount('procedure_requests', 0);
    }

    public function test_staff_searches_by_document_email_or_student_code(): void
    {
        [$staff] = $this->context();
        $applicant = User::factory()->for($this->role('Solicitante'))->create(['document_type' => 'DNI', 'document_number' => 'SEARCH-01', 'email' => 'buscar@example.test', 'student_code' => 'EST-100']);
        foreach ([['document_type' => 'DNI', 'document_number' => 'SEARCH-01'], ['email' => 'buscar@example.test'], ['student_code' => 'EST-100']] as $filters) {
            $this->actingAs($staff)->get(route('front-desk.physical-registration.create', $filters))->assertOk()->assertSee($applicant->document_number);
        }
    }

    public function test_new_applicant_with_email_receives_secure_claim_link(): void
    {
        Notification::fake();
        [$staff, $type, $requirement] = $this->context();
        $this->actingAs($staff)->post(route('front-desk.physical-registration.store'), $this->payload($type, $requirement, ['document_type' => 'CE', 'document_number' => 'CLAIM-01', 'first_name' => 'Cuenta', 'last_name' => 'Pendiente', 'academic_program' => 'Programa', 'academic_condition' => 'Regular', 'email' => 'claim@example.test']))->assertRedirect();
        $applicant = User::where('document_number', 'CLAIM-01')->firstOrFail();
        $this->assertTrue($applicant->account_claim_pending);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'claim@example.test']);
        Notification::assertSentTo($applicant, ResetPasswordNotification::class);
    }

    public function test_complete_and_incomplete_receptions_preserve_exact_documents(): void
    {
        [$staff, $type, $requirement] = $this->context();
        $first = User::factory()->for($this->role('Solicitante'))->create();
        $this->actingAs($staff)->post(route('front-desk.physical-registration.store'), $this->payload($type, $requirement, ['existing_user_id' => $first->id, 'confirm_existing_identity' => '1']))->assertRedirect();
        $complete = ProcedureRequest::firstOrFail();
        $this->assertSame('complete', $complete->physicalReception->verification_result);
        $this->assertSame('legalized_copy', $complete->physicalReception->presented_documents[0]['presentation']);

        $second = User::factory()->for($this->role('Solicitante'))->create();
        $data = $this->payload($type, $requirement, ['existing_user_id' => $second->id, 'confirm_existing_identity' => '1']);
        unset($data['received_documents']);
        $this->post(route('front-desk.physical-registration.store'), $data)->assertRedirect();
        $incomplete = ProcedureRequest::latest('id')->firstOrFail();
        $this->assertSame('incomplete', $incomplete->physicalReception->verification_result);
        $this->assertSame($requirement->name, $incomplete->configuration_snapshot['pending_requirements'][0]['name']);
    }

    public function test_receipt_notification_role_restriction_and_audit(): void
    {
        [$staff, $type, $requirement] = $this->context();
        $applicant = User::factory()->for($this->role('Solicitante'))->create();
        $this->actingAs($applicant)->get(route('front-desk.physical-registration.create'))->assertForbidden();
        $this->post(route('front-desk.physical-registration.store'), [])->assertForbidden();

        $this->actingAs($staff)->post(route('front-desk.physical-registration.store'), $this->payload($type, $requirement, ['existing_user_id' => $applicant->id, 'confirm_existing_identity' => '1']))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $this->get(route('front-desk.physical-registration.receipt', $request))->assertOk()->assertSee('Constancia de recepción')->assertSee($request->tracking_code)->assertSee($requirement->name);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'physical_case_registered']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'physical_case_registered']);

        $admin = User::factory()->for($this->role('Administrador'))->create(['active' => true]);
        $this->actingAs($admin)->get(route('front-desk.physical-registration.create'))->assertOk();
    }

    private function context(): array
    {
        $this->role('Solicitante');
        $staff = User::factory()->for($this->role('Mesa de Partes'))->create(['active' => true]);
        $modality = PresentationModality::factory()->create(['code' => PresentationModeCode::IN_PERSON]);
        $type = ProcedureType::factory()->for($modality, 'presentationModality')->create(['active' => true, 'reception_open' => true]);
        $requirement = ProcedureRequirement::factory()->for($type)->create(['name' => 'Original obligatorio ficticio', 'required' => true, 'requires_physical_submission' => true, 'requires_digital_file' => false]);

        return [$staff, $type, $requirement];
    }

    private function payload(ProcedureType $type, ProcedureRequirement $requirement, array $extra = []): array
    {
        return ['procedure_type_id' => $type->id, 'received_at' => now()->subMinute()->toDateTimeString(), 'folio_count' => 5, 'received_documents' => [$requirement->id => ['received' => '1', 'presentation' => 'legalized_copy', 'quantity' => 1]], ...$extra];
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], ['description' => 'Rol ficticio', 'active' => true]);
    }
}
