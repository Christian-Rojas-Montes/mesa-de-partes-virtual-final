<?php

namespace Tests\Feature\Title;

use App\Enums\TitleModality;
use App\Enums\TitleProcessStage;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\InstitutionalCatalogSyncService;
use App\Services\TitleProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TitleProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $staff;

    private ProcedureRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstitutionalCatalogSyncService::class)->synchronize(true);
        $this->applicant = $this->user('Solicitante');
        $this->staff = $this->user('Mesa de Partes');
        $status = Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado']);
        $this->request = ProcedureRequest::factory()->create(['user_id' => $this->applicant->id, 'procedure_type_id' => ProcedureType::where('code', 'TITLE_PROF_TECH')->value('id'), 'status_id' => $status->id]);
    }

    public function test_creation_preserves_modality_declared_eligibility_and_history(): void
    {
        $process = $this->createProcess();
        $this->assertSame(TitleModality::APPLICATION_WORK, $process->modality);
        $this->assertSame('Desarrollo de Sistemas', $process->eligibility_declared['programa_estudios']);
        $this->assertSame(TitleProcessStage::INITIAL_FILE, $process->current_stage);
        $this->assertDatabaseHas('title_stage_events', ['title_process_id' => $process->id, 'action' => 'created']);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $this->request->id, 'action' => 'title_created']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $this->request->id, 'action' => 'title_created']);
    }

    public function test_stage_transitions_reject_invalid_changes_and_final_file_requires_approval(): void
    {
        $service = app(TitleProcessService::class);
        $process = $this->createProcess();
        try {
            $service->transition($process, $this->staff, TitleProcessStage::FINAL_FILE, 'Salto inválido');
            $this->fail('La transición inválida debió rechazarse.');
        } catch (ValidationException) {
            $this->assertSame(TitleProcessStage::INITIAL_FILE, $process->fresh()->current_stage);
        }
        $service->transition($process, $this->staff, TitleProcessStage::REQUIREMENTS_REVIEW, 'Revisión iniciada');
        $service->transition($process->fresh(), $this->staff, TitleProcessStage::ACADEMIC_AREA, 'Expediente conforme y derivado');
        $this->assertSame(TitleProcessStage::ACADEMIC_AREA, $process->fresh()->current_stage);
    }

    public function test_programming_reprogramming_result_and_final_file_are_traceable(): void
    {
        $service = app(TitleProcessService::class);
        $process = $this->createProcess();
        $service->transition($process, $this->staff, TitleProcessStage::REQUIREMENTS_REVIEW, 'Revisión');
        $service->transition($process->fresh(), $this->staff, TitleProcessStage::ACADEMIC_AREA, 'Derivación académica');
        $schedule = $service->schedule($process->fresh(), $this->staff, ['scheduled_at' => now()->addWeek(), 'place' => 'Sala ficticia', 'jury_or_responsibles' => ['Responsable ficticio']]);
        $replacement = $service->schedule($process->fresh(), $this->staff, ['scheduled_at' => now()->addWeeks(2), 'place' => 'Auditorio ficticio', 'reason' => 'Reprogramación ficticia'], $schedule);
        $this->assertSame('rescheduled', $schedule->fresh()->status);
        $this->assertSame($schedule->id, $replacement->rescheduled_from_id);
        $service->recordResult($process->fresh(), $this->staff, 'approved', 'Resultado sin calificación detallada.');
        $service->transition($process->fresh(), $this->staff, TitleProcessStage::FINAL_FILE, 'Expediente final conformado');
        $this->assertSame(TitleProcessStage::FINAL_FILE, $process->fresh()->current_stage);
        $this->assertNotNull($process->fresh()->final_file_completed_at);
    }

    public function test_eligibility_documents_role_access_and_private_act_metadata(): void
    {
        $service = app(TitleProcessService::class);
        $process = $this->createProcess();
        $service->verifyEligibility($process, $this->staff, ['graduate_status' => true, 'language' => true, 'practice_efsrt' => true]);
        $document = RequestDocument::create(['procedure_request_id' => $this->request->id, 'disk' => 'private', 'path' => 'titles/acta.pdf', 'stored_name' => 'acta.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'checksum_sha256' => str_repeat('a', 64)]);
        $service->attachDocument($process, $document, $this->staff, 'minutes', 'Acta privada ficticia');
        $outsider = $this->user('Solicitante');
        $this->actingAs($outsider)->get(route('title-processes.show', $process))->assertForbidden();
        $this->actingAs($applicant = $this->applicant)->get(route('title-processes.show', $process))->assertOk()->assertDontSee('Acta privada ficticia')->assertDontSee('Responsable:');
        $this->actingAs($this->staff)->get(route('title-processes.show', $process))->assertOk()->assertSee('Responsable:');
        $this->actingAs($applicant)->post(route('title-processes.transition', $process), ['stage' => 'requirements_review', 'description' => 'No autorizado'])->assertForbidden();
    }

    private function createProcess()
    {
        return app(TitleProcessService::class)->create($this->request, $this->applicant, TitleModality::APPLICATION_WORK, [
            'programa_estudios' => 'Desarrollo de Sistemas', 'condicion_egresado' => 'Declarada', 'fecha_egreso' => '2025-12-15',
            'sistema_academico' => 'Modular', 'idioma_lengua' => 'Declarado', 'practicas_efsrt' => 'Declaradas',
        ], 'Convocatoria ficticia 1');
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['description' => 'Rol ficticio', 'active' => true]);

        return User::factory()->for($role)->create(['active' => true]);
    }
}
