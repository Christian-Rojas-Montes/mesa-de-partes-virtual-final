<?php

namespace Tests\Feature\Workflow;

use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestObservation;
use App\Models\RequestResponse;
use App\Models\User;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompleteProcedureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrivateDocumentStorage::DISK);
        $this->seed();
    }

    public function test_complete_procedure_flow_uses_real_routes_permissions_and_private_files(): void
    {
        $password = 'FlujoDemo9!';

        $this->post(route('register'), [
            'document_type' => 'OTRO',
            'document_number' => 'FLUJO-FICT-001',
            'first_name' => 'Flujo',
            'last_name' => 'Integral Ficticio',
            'email' => 'flujo.integral@example.test',
            'phone' => null,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('dashboard.applicant'));

        $applicant = User::query()->where('email', 'flujo.integral@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($applicant);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => $applicant->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard.applicant'));

        $procedureType = ProcedureType::query()->where('code', 'SOL-GEN')->firstOrFail();
        $requirements = $procedureType->activeRequirements()->orderBy('id')->get();
        $documents = [];

        foreach ($requirements as $requirement) {
            $documents[$requirement->id] = $this->validPdf("requisito-{$requirement->id}.pdf");
        }

        $this->post(route('applicant.procedure-requests.store'), [
            'procedure_type_id' => $procedureType->id,
            'subject' => 'Solicitud ficticia de validación integral',
            'description' => 'Expediente completamente ficticio para validar el flujo local del sistema.',
            'documents' => $documents,
            'confirmation' => '1',
        ])->assertRedirect();

        $procedureRequest = ProcedureRequest::query()
            ->where('user_id', $applicant->id)
            ->with(['documents', 'status'])
            ->firstOrFail();
        $this->assertMatchesRegularExpression('/^MPV-'.now()->format('Y').'-\d{6}$/', $procedureRequest->tracking_code);
        $this->assertSame('REGISTRADO', $procedureRequest->status->code);
        $this->assertCount($requirements->count(), $procedureRequest->documents);

        foreach ($procedureRequest->documents as $document) {
            $this->assertSame(PrivateDocumentStorage::DISK, $document->disk);
            $this->assertStringNotContainsString('requisito-', $document->stored_name);
            Storage::disk(PrivateDocumentStorage::DISK)->assertExists($document->path);
        }

        $frontDesk = $this->developmentUser('mesa.partes@example.test');
        $manager = $this->developmentUser('responsable.area@example.test');
        $administrator = $this->developmentUser('administrador@example.test');

        $this->actingAs($applicant)->get(route('front-desk.reviews.index'))->assertForbidden();
        $this->actingAs($frontDesk)->get(route('area-manager.assignments.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($administrator)->get(route('applicant.procedure-requests.index'))->assertForbidden();

        $this->actingAs($frontDesk)
            ->get(route('front-desk.reviews.show', $procedureRequest))
            ->assertOk()
            ->assertSee($procedureRequest->tracking_code);
        $this->patch(route('front-desk.reviews.start', $procedureRequest))->assertSessionHas('status');
        $this->post(route('front-desk.reviews.observe', $procedureRequest), [
            'description' => 'El documento ficticio requiere una versión más legible.',
            'correction_instructions' => 'Adjuntar una nueva copia ficticia en formato PDF.',
        ])->assertSessionHas('status');

        $observation = RequestObservation::query()->where('procedure_request_id', $procedureRequest->id)->firstOrFail();
        $this->assertSame('OBSERVADO', $procedureRequest->fresh()->status->code);

        $this->actingAs($applicant)
            ->get(route('applicant.procedure-requests.corrections.create', $procedureRequest))
            ->assertOk()
            ->assertSee($observation->description);
        $this->post(route('applicant.procedure-requests.corrections.store', $procedureRequest), [
            'observation_id' => $observation->id,
            'message' => 'Se presenta una subsanación completamente ficticia.',
            'documents' => ['general' => $this->validPdf('subsanacion-ficticia.pdf')],
        ])->assertRedirect(route('applicant.procedure-requests.show', $procedureRequest));

        $this->actingAs($frontDesk)
            ->patch(route('front-desk.reviews.validate', $procedureRequest))
            ->assertSessionHas('status');
        $this->post(route('front-desk.derivations.store', $procedureRequest), [
            'area_id' => $manager->area_id,
            'reason' => 'Atención por el área ficticia competente.',
        ])->assertSessionHas('status');

        $derivation = $procedureRequest->fresh()->latestDerivation()->firstOrFail();
        $this->actingAs($manager)
            ->get(route('area-manager.assignments.show', $procedureRequest))
            ->assertOk()
            ->assertSee($procedureRequest->tracking_code);
        $this->patch(route('area-manager.assignments.receive', [$procedureRequest, $derivation]))
            ->assertSessionHas('status');
        $this->patch(route('area-manager.assignments.attention.start', $procedureRequest))
            ->assertSessionHas('status');
        $this->post(route('area-manager.assignments.attention-actions.store', $procedureRequest), [
            'description' => 'Se revisó el expediente ficticio y se preparó la respuesta.',
        ])->assertSessionHas('status');
        $this->post(route('area-manager.assignments.response.store', $procedureRequest), [
            'summary' => 'Respuesta final ficticia emitida durante la validación integral.',
            'document' => $this->validPdf('respuesta-ficticia.pdf'),
        ])->assertSessionHas('status');

        $response = RequestResponse::query()->where('procedure_request_id', $procedureRequest->id)->firstOrFail();
        $this->assertSame(PrivateDocumentStorage::DISK, $response->disk);
        Storage::disk(PrivateDocumentStorage::DISK)->assertExists($response->path);

        $this->actingAs($applicant)
            ->get(route('applicant.procedure-requests.response.download', $procedureRequest))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get(route('applicant.procedure-requests.show', $procedureRequest))
            ->assertOk()
            ->assertSee('Respuesta final ficticia')
            ->assertSee('Se revisó el expediente ficticio');
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($procedureRequest->tracking_code);

        $this->actingAs($frontDesk)
            ->get(route('search.index', ['codigo' => $procedureRequest->tracking_code]))
            ->assertOk()
            ->assertSee($procedureRequest->tracking_code);
        $this->get(route('front-desk.closures.response.download', $procedureRequest))->assertOk();
        $this->patch(route('front-desk.closures.finalize', $procedureRequest))->assertSessionHas('status');

        $this->assertSame('FINALIZADO', $procedureRequest->fresh()->status->code);
        $this->actingAs($applicant)
            ->get(route('applicant.procedure-requests.show', $procedureRequest))
            ->assertOk()
            ->assertSee('Finalizado');

        $report = $this->actingAs($administrator)
            ->get(route('reports.index'))
            ->assertOk()
            ->viewData('report');
        $this->assertSame(1, (int) $report['summary']->total);
        $this->get(route('admin.audit-logs.index', ['entidad' => ProcedureRequest::class]))
            ->assertOk()
            ->assertSee('Solicitud #'.$procedureRequest->id);

        foreach ([
            'registered', 'review_started', 'observed', 'correction_submitted', 'validated',
            'derived', 'derivation_received', 'attention_started', 'attention_action',
            'response_registered', 'request_finalized',
        ] as $action) {
            $this->assertDatabaseHas('request_histories', [
                'procedure_request_id' => $procedureRequest->id,
                'action' => $action,
            ]);
        }

        $this->assertGreaterThanOrEqual(11, AuditLog::query()
            ->where('auditable_type', ProcedureRequest::class)
            ->where('auditable_id', $procedureRequest->id)
            ->count());
        $this->assertGreaterThanOrEqual(9, $applicant->notifications()->count());
    }

    private function developmentUser(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function validPdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );
    }
}
