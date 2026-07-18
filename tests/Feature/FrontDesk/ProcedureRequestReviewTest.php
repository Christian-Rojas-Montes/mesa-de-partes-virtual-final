<?php

namespace Tests\Feature\FrontDesk;

use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\RequestObservation;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcedureRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateDocumentStorage::DISK);
        $this->seedStatuses();
    }

    public function test_review_queue_contains_only_registered_and_in_review_requests_and_supports_filters(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $type = ProcedureType::factory()->create(['name' => 'Trámite ficticio filtrado']);
        $otherType = ProcedureType::factory()->create();
        $target = $this->request($applicant, $type, 'EN_REVISION', [
            'tracking_code' => 'MPV-2026-007777',
            'subject' => 'Solicitud objetivo de revisión',
            'submitted_at' => '2026-07-10 10:00:00',
        ]);
        $this->request($applicant, $type, 'REGISTRADO', ['subject' => 'Otra registrada']);
        $this->request($applicant, $otherType, 'EN_REVISION', ['subject' => 'Otro trámite']);
        $this->request($applicant, $type, 'OBSERVADO', ['subject' => 'No debe estar en bandeja']);
        $this->request($applicant, $type, 'RECHAZADO', ['subject' => 'Rechazada fuera de bandeja']);

        $this->actingAs($reviewer)->get(route('front-desk.reviews.index', [
            'codigo' => '007777',
            'fecha' => '2026-07-10',
            'tramite' => $type->id,
            'estado' => $this->findStatus('EN_REVISION')->id,
        ]))->assertOk()
            ->assertSee($target->subject)
            ->assertDontSee('Otra registrada')
            ->assertDontSee('Otro trámite')
            ->assertDontSee('No debe estar en bandeja')
            ->assertDontSee('Rechazada fuera de bandeja');
    }

    public function test_registered_request_can_start_review_only_once_with_history_audit_and_notification(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $procedureRequest = $this->request($applicant, ProcedureType::factory()->create(), 'REGISTRADO');

        $this->actingAs($reviewer)->patch(route('front-desk.reviews.start', $procedureRequest))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame('EN_REVISION', $procedureRequest->fresh()->status->code);
        $this->assertDatabaseHas('request_histories', [
            'procedure_request_id' => $procedureRequest->id,
            'user_id' => $reviewer->id,
            'action' => 'review_started',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reviewer->id,
            'action' => 'review_started',
            'auditable_id' => $procedureRequest->id,
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);

        $historyCount = $procedureRequest->histories()->count();
        $this->patch(route('front-desk.reviews.start', $procedureRequest))->assertSessionHasErrors('action');
        $this->assertSame($historyCount, $procedureRequest->histories()->count());
    }

    public function test_validation_is_recorded_and_cannot_be_repeated(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $procedureRequest = $this->request($applicant, ProcedureType::factory()->create(), 'EN_REVISION');

        $this->actingAs($reviewer)->patch(route('front-desk.reviews.validate', $procedureRequest))->assertSessionHas('status');
        $procedureRequest->refresh();
        $this->assertNotNull($procedureRequest->validated_at);
        $this->assertSame($reviewer->id, $procedureRequest->validated_by);
        $this->assertSame('EN_REVISION', $procedureRequest->status->code);

        $validatedHistoryCount = $procedureRequest->histories()->where('action', 'validated')->count();
        $this->patch(route('front-desk.reviews.validate', $procedureRequest))->assertSessionHasErrors('action');
        $this->assertSame($validatedHistoryCount, $procedureRequest->histories()->where('action', 'validated')->count());
    }

    public function test_observation_requires_reason_preserves_responsible_and_notifies_applicant(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $procedureRequest = $this->request($applicant, ProcedureType::factory()->create(), 'EN_REVISION');

        $this->actingAs($reviewer)->post(route('front-desk.reviews.observe', $procedureRequest), [
            'description' => '',
        ])->assertSessionHasErrors('description');

        $this->post(route('front-desk.reviews.observe', $procedureRequest), [
            'description' => 'El documento ficticio no es legible.',
            'correction_instructions' => 'Adjunta una nueva copia ficticia.',
            'correction_deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertSessionHas('status');

        $observation = RequestObservation::firstOrFail();
        $this->assertSame($reviewer->id, $observation->user_id);
        $this->assertNotNull($observation->created_at);
        $this->assertSame('OBSERVADO', $procedureRequest->fresh()->status->code);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);

        $this->post(route('front-desk.reviews.observe', $procedureRequest), [
            'description' => 'Observación repetida no permitida.',
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('request_observations', 1);
    }

    public function test_rejection_requires_justification_is_persistent_and_cannot_be_repeated(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $procedureRequest = $this->request($applicant, ProcedureType::factory()->create(), 'EN_REVISION');

        $this->actingAs($reviewer)->post(route('front-desk.reviews.reject', $procedureRequest), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->post(route('front-desk.reviews.reject', $procedureRequest), [
            'reason' => 'El trámite ficticio no corresponde al procedimiento solicitado.',
        ])->assertSessionHas('status');

        $this->assertSame('RECHAZADO', $procedureRequest->fresh()->status->code);
        $this->assertDatabaseHas('request_rejections', [
            'procedure_request_id' => $procedureRequest->id,
            'user_id' => $reviewer->id,
            'reason' => 'El trámite ficticio no corresponde al procedimiento solicitado.',
        ]);
        $this->assertDatabaseHas('procedure_requests', ['id' => $procedureRequest->id]);

        $this->post(route('front-desk.reviews.reject', $procedureRequest), [
            'reason' => 'Segundo rechazo no permitido.',
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('request_rejections', 1);
    }

    public function test_applicant_correction_preserves_old_documents_resolves_observation_and_returns_to_review(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $procedureRequest = $this->request($applicant, ProcedureType::factory()->create(), 'OBSERVADO');
        $observation = RequestObservation::factory()->for($procedureRequest)->create([
            'user_id' => $reviewer->id,
            'description' => 'Corrige el documento ficticio.',
            'resolved_at' => null,
        ]);
        Storage::disk('private')->put('requests/original.pdf', '%PDF original');
        $originalDocument = $this->document($procedureRequest, 'requests/original.pdf');

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.corrections.create', $procedureRequest))
            ->assertOk()->assertSee($observation->description);
        $this->post(route('applicant.procedure-requests.corrections.store', $procedureRequest), [
            'observation_id' => $observation->id,
            'message' => 'Adjunto la corrección ficticia solicitada.',
            'documents' => ['general1' => $this->validPdf()],
        ])->assertRedirect(route('applicant.procedure-requests.show', $procedureRequest));

        $procedureRequest->refresh();
        $this->assertSame('EN_REVISION', $procedureRequest->status->code);
        $this->assertNotNull($observation->fresh()->resolved_at);
        $this->assertDatabaseHas('request_corrections', [
            'procedure_request_id' => $procedureRequest->id,
            'request_observation_id' => $observation->id,
            'user_id' => $applicant->id,
        ]);
        $this->assertDatabaseHas('request_documents', ['id' => $originalDocument->id]);
        $this->assertSame(2, $procedureRequest->documents()->count());
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $procedureRequest->id, 'action' => 'correction_submitted']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $applicant->id, 'action' => 'correction_submitted']);

        $this->post(route('applicant.procedure-requests.corrections.store', $procedureRequest), [
            'observation_id' => $observation->id,
            'documents' => ['general1' => $this->validPdf()],
        ])->assertForbidden();
        $this->assertDatabaseCount('request_corrections', 1);
    }

    public function test_permissions_and_secure_document_access_are_enforced(): void
    {
        $reviewer = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $otherApplicant = $this->userWithRole('Solicitante', 'otro.permiso@example.test');
        $administrator = $this->userWithRole('Administrador');
        $procedureRequest = $this->request($applicant, ProcedureType::factory()->create(), 'REGISTRADO');
        Storage::disk('private')->put('requests/review.pdf', '%PDF revisión');
        $document = $this->document($procedureRequest, 'requests/review.pdf');

        $this->actingAs($reviewer)->get(route('front-desk.reviews.show', $procedureRequest))->assertOk();
        $this->get(route('front-desk.reviews.documents.download', [$procedureRequest, $document]))->assertOk();

        foreach ([$applicant, $administrator] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('front-desk.reviews.index'))->assertForbidden();
            $this->get(route('front-desk.reviews.show', $procedureRequest))->assertForbidden();
            $this->patch(route('front-desk.reviews.start', $procedureRequest))->assertForbidden();
        }

        $this->actingAs($otherApplicant)->get(route('applicant.procedure-requests.corrections.create', $procedureRequest))->assertForbidden();
    }

    private function seedStatuses(): void
    {
        foreach ([
            ['REGISTRADO', 'Registrado'],
            ['EN_REVISION', 'En revisión'],
            ['OBSERVADO', 'Observado'],
            ['DERIVADO', 'Derivado'],
            ['RECHAZADO', 'Rechazado'],
        ] as $index => [$code, $name]) {
            Status::query()->create([
                'code' => $code,
                'name' => $name,
                'description' => "Estado ficticio {$name}.",
                'sort_order' => $index + 1,
                'active' => true,
            ]);
        }
    }

    private function findStatus(string $code): Status
    {
        return Status::where('code', $code)->firstOrFail();
    }

    private function userWithRole(string $roleName, ?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], [
            'description' => "Rol ficticio {$roleName}.",
            'active' => true,
        ]);

        return User::factory()->for($role)->create([
            'area_id' => null,
            'email' => $email ?? fake()->unique()->userName().'@example.test',
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function request(User $applicant, ProcedureType $type, string $statusCode, array $attributes = []): ProcedureRequest
    {
        return ProcedureRequest::factory()->create([
            'user_id' => $applicant->id,
            'procedure_type_id' => $type->id,
            'status_id' => $this->findStatus($statusCode)->id,
            ...$attributes,
        ]);
    }

    private function document(ProcedureRequest $procedureRequest, string $path): RequestDocument
    {
        return RequestDocument::query()->create([
            'procedure_request_id' => $procedureRequest->id,
            'disk' => 'private',
            'path' => $path,
            'stored_name' => basename($path),
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'checksum_sha256' => str_repeat('a', 64),
        ]);
    }

    private function validPdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'correccion-ficticia.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );
    }
}
