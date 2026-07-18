<?php

namespace Tests\Feature\Title;

use App\Enums\ApplicationWorkStage;
use App\Enums\TitleModality;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\ApplicationWorkService;
use App\Services\InstitutionalCatalogSyncService;
use App\Services\TitleProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $staff;

    private $process;

    private ApplicationWorkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstitutionalCatalogSyncService::class)->synchronize(true);
        $this->applicant = $this->user('Solicitante');
        $this->staff = $this->user('Mesa de Partes');
        $status = Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado']);
        $request = ProcedureRequest::factory()->create(['user_id' => $this->applicant->id, 'procedure_type_id' => ProcedureType::where('code', 'TITLE_PROF_TECH')->value('id'), 'status_id' => $status->id]);
        $this->process = app(TitleProcessService::class)->create($request, $this->applicant, TitleModality::APPLICATION_WORK, ['programa_estudios' => 'Sistemas']);
        $this->service = app(ApplicationWorkService::class);
    }

    public function test_valid_group_and_more_than_configured_members(): void
    {
        $project = $this->proposal(4);
        $this->assertCount(4, $project->members);
        $this->assertSame(ApplicationWorkStage::PROPOSAL, $project->current_stage);
        $request = $this->newProcess();
        $members = array_fill(0, 5, ['name' => 'Integrante ficticio', 'study_program' => 'Sistemas']);
        $this->expectException(ValidationException::class);
        $this->service->createProposal($request, $this->applicant, $this->proposalData($request), $members);
    }

    public function test_approval_resolution_execution_period_and_history(): void
    {
        $project = $this->proposal();
        $resolution = $this->document();
        $this->service->approve($project, $this->staff, ['review_result' => 'approved', 'approval_observations' => 'Conforme', 'approval_resolution_document_id' => $resolution->id, 'assigned_advisor' => 'Asesor ficticio', 'approved_at' => '2026-01-01', 'execution_deadline' => '2026-04-01']);
        $project->refresh();
        $this->assertSame(ApplicationWorkStage::GRADUATE_CERTIFICATE, $project->current_stage);
        $this->assertSame($resolution->id, $project->approval_resolution_document_id);
        $this->assertDatabaseHas('application_work_events', ['application_work_project_id' => $project->id, 'action' => 'proposal_reviewed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_work_proposal_reviewed']);
    }

    public function test_documents_physical_originals_programming_and_result(): void
    {
        $project = $this->approved();
        foreach ($project->requirements()->where('stage', 'graduate_certificate')->get() as $item) {
            $this->service->registerRequirement($project->fresh(), $this->staff, $item->stage, $item->code, 'verified', $this->document()->id);
        } $this->assertSame(ApplicationWorkStage::DEFENSE_FILE, $project->fresh()->current_stage);
        foreach ($project->requirements()->where('stage', 'defense_file')->get() as $item) {
            $this->service->registerRequirement($project->fresh(), $this->staff, $item->stage, $item->code, 'verified', $item->physical ? null : $this->document()->id);
        } $project->refresh();
        $physical = $project->requirements()->where('code', 'physical_copies')->first();
        $this->assertTrue($physical->physical);
        $this->assertSame(3, $physical->quantity);
        $schedule = $this->service->schedule($project, $this->staff, ['scheduled_at' => now()->addWeek(), 'place' => 'Auditorio ficticio', 'jury_or_responsibles' => ['Jurado ficticio']]);
        $this->assertNotNull($schedule);
        $minutes = $this->document();
        $this->service->result($project->fresh(), $this->staff, 'approved', 13, $minutes->id, 'Aprobado');
        $this->assertSame('approved', $this->process->fresh()->result);
        $this->assertSame($minutes->id, $project->fresh()->result_minutes_document_id);
    }

    public function test_similarity_is_configured_and_permissions_are_enforced(): void
    {
        $project = $this->proposal();
        config()->set('title-process.application_work.similarity_max_percent', 20);
        $this->expectException(ValidationException::class);
        try {
            $this->service->registerOriginality($project, $this->staff, 21, 'conforming');
        } finally {
            $this->actingAs($this->applicant)->post(route('title-processes.application-work.approve', [$this->process, $project]), ['review_result' => 'observed'])->assertForbidden();
        }
    }

    private function proposal(int $count = 1)
    {
        $members = [];
        for ($i = 1; $i <= $count; $i++) {
            $members[] = ['name' => "Integrante ficticio {$i}", 'study_program' => 'Sistemas'];
        }

return $this->service->createProposal($this->process, $this->applicant, $this->proposalData($this->process), $members);
    }

    private function approved()
    {
        $project = $this->proposal();
        $this->service->approve($project, $this->staff, ['review_result' => 'approved', 'approval_resolution_document_id' => $this->document()->id, 'assigned_advisor' => 'Asesor ficticio', 'approved_at' => '2026-01-01', 'execution_deadline' => '2026-04-01']);

        return $project->fresh();
    }

    private function proposalData($process): array
    {
        return ['title' => 'Proyecto ficticio', 'problem' => 'Problema ficticio', 'objective' => 'Objetivo ficticio', 'study_program' => 'Sistemas', 'proposed_advisor' => 'Asesor propuesto', 'project_document_id' => $this->document($process)->id, 'proposal_date' => '2026-01-01'];
    }

    private function document($process = null): RequestDocument
    {
        $requestId = ($process ?? $this->process)->procedure_request_id;

        return RequestDocument::create(['procedure_request_id' => $requestId, 'disk' => 'private', 'path' => 'titles/'.uniqid().'.pdf', 'stored_name' => 'doc.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'checksum_sha256' => hash('sha256', uniqid())]);
    }

    private function newProcess()
    {
        $status = Status::first();
        $request = ProcedureRequest::factory()->create(['user_id' => $this->applicant->id, 'procedure_type_id' => ProcedureType::where('code', 'TITLE_PROF_TECH')->value('id'), 'status_id' => $status->id]);

        return app(TitleProcessService::class)->create($request, $this->applicant, TitleModality::APPLICATION_WORK, ['programa_estudios' => 'Sistemas']);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName],['description' => 'Rol ficticio', 'active' => true]);

        return User::factory()->for($role)->create(['active' => true]);
    }
}
