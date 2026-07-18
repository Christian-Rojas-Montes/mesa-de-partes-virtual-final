<?php

namespace Tests\Feature\Applicant;

use App\Enums\PresentationModeCode;
use App\Enums\RequirementType;
use App\Models\PresentationModality;
use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcedurePresentationModalityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateDocumentStorage::DISK);
        Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado', 'active' => true]);
    }

    public function test_digital_request_is_immediately_presented_without_physical_delivery(): void
    {
        [$applicant, $type] = $this->context(PresentationModeCode::DIGITAL);
        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), $this->payload($type))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $this->assertSame('REGISTRADO', $request->status->code);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'registered']);
        $this->get(route('applicant.procedure-requests.show', $request))->assertOk()->assertSee('presentada digitalmente');
    }

    public function test_hybrid_request_is_pending_until_front_desk_confirms_physical_reception(): void
    {
        [$applicant, $type] = $this->context(PresentationModeCode::HYBRID, ['requires_physical_delivery' => true, 'physical_submission_location' => 'Oficina ficticia', 'physical_submission_schedule' => 'Lunes a viernes', 'physical_submission_deadline_days' => 5]);
        ProcedureRequirement::factory()->for($type)->create(['name' => 'Original ficticio', 'type' => RequirementType::PHYSICAL_DOCUMENT, 'required' => true, 'requires_digital_file' => false, 'requires_physical_submission' => true, 'requires_original' => true]);
        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), $this->payload($type))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'pending_physical_delivery']);
        $this->get(route('applicant.procedure-requests.show', $request))->assertOk()->assertSee('Pendiente de confirmación')->assertSee('Original ficticio')->assertSee('Oficina ficticia');
    }

    public function test_in_person_registration_is_only_a_pre_registration(): void
    {
        [$applicant, $type] = $this->context(PresentationModeCode::IN_PERSON, ['requires_physical_delivery' => true]);
        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), $this->payload($type))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'pre_registration_created']);
        $this->assertNull($request->physicalReception);
    }

    public function test_only_front_desk_confirms_reception_with_history_audit_and_generic_notification(): void
    {
        [$applicant, $type] = $this->context(PresentationModeCode::HYBRID, ['requires_physical_delivery' => true]);
        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), $this->payload($type))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $data = ['received_at' => now()->subMinute()->toDateTimeString(), 'folio_count' => 4, 'document_count' => 1, 'presented_documents' => [['name' => 'Documento ficticio', 'presentation' => 'original', 'quantity' => 1]], 'verification_result' => 'originals_verified'];

        $this->post(route('front-desk.reviews.physical-reception.confirm', $request), $data)->assertForbidden();
        $frontDesk = User::factory()->for($this->role('Mesa de Partes'))->create(['active' => true]);
        $this->actingAs($frontDesk)->post(route('front-desk.reviews.physical-reception.confirm', $request), $data)->assertRedirect();

        $this->assertDatabaseHas('request_physical_receptions', ['procedure_request_id' => $request->id, 'received_by' => $frontDesk->id, 'verification_result' => 'originals_verified']);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'physical_documents_received']);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'originals_verified']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'physical_reception_confirmed']);
        $notification = $applicant->notifications()->where('data->event', 'physical_reception_confirmed')->firstOrFail();
        $this->assertStringNotContainsString('Documento ficticio', $notification->data['message']);
        $this->assertSame('REGISTRADO', $request->fresh()->status->code);
    }

    private function context(PresentationModeCode $mode, array $attributes = []): array
    {
        $applicant = User::factory()->for($this->role('Solicitante'))->create(['active' => true, 'area_id' => null]);
        $modality = PresentationModality::factory()->create(['code' => $mode, 'name' => $mode->value]);
        $type = ProcedureType::factory()->for($modality, 'presentationModality')->create([...$attributes, 'active' => true, 'allows_digital_registration' => true]);
        ProcedureRequirement::factory()->for($type)->create(['required' => false]);

        return [$applicant, $type];
    }

    private function payload(ProcedureType $type): array
    {
        return ['procedure_type_id' => $type->id, 'subject' => 'Solicitud ficticia', 'description' => 'Descripción ficticia para modalidad.', 'confirmation' => '1'];
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], ['description' => 'Rol ficticio', 'active' => true]);
    }
}
