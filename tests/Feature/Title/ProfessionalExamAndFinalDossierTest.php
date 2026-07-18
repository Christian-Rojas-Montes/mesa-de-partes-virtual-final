<?php

namespace Tests\Feature\Title;

use App\Enums\TitleModality;
use App\Enums\TitleProcessStage;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\InstitutionalCatalogSyncService;
use App\Services\ProfessionalExamService;
use App\Services\TitleFinalDossierService;
use App\Services\TitleProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfessionalExamAndFinalDossierTest extends TestCase
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

    public function test_minimum_experience_requirements_weights_grade_and_history_are_enforced(): void
    {
        config(['title-process.professional_exam.minimum_experience_months' => 6, 'title-process.professional_exam.theory_weight' => 30, 'title-process.professional_exam.practical_weight' => 70, 'title-process.professional_exam.passing_grade' => 13]);
        $process = $this->process();
        $service = app(ProfessionalExamService::class);
        try {
            $service->create($process, $this->applicant, 5, 'efsrt');
            $this->fail();
        } catch (ValidationException) {
            $this->assertFalse($process->professionalExamProfile()->exists());
        }
        $profile = $service->create($process, $this->applicant, 6, 'efsrt');
        $this->assertCount(8, $profile->requirements);
        $this->assertSame(3, $profile->requirements->firstWhere('code', 'title_minutes')->quantity);
        foreach ($profile->requirements as $requirement) {
            $service->verifyRequirement($profile, $this->staff, $requirement->code, 'verified');
        }
        $attempt = $service->schedule($profile, $this->staff, ['scheduled_at' => now()->addDay(), 'place' => 'Auditorio ficticio', 'jury_or_responsibles' => ['Jurado ficticio']]);
        $service->recordResult($attempt, $this->staff, 'approved', 10, 14);
        $this->assertSame('12.80', $attempt->fresh()->final_grade);
        $this->assertSame('failed', $attempt->fresh()->result);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $this->request->id, 'action' => 'title_exam_result_recorded']);
    }

    public function test_opportunities_are_configurable_and_permission_is_server_side(): void
    {
        config(['title-process.professional_exam.maximum_opportunities' => 1]);
        $profile = app(ProfessionalExamService::class)->create($this->process(), $this->applicant, 6, 'professional');
        $outsider = $this->user('Solicitante');
        $this->assertFalse($outsider->can('manage', $profile->titleProcess));
        foreach ($profile->requirements as $requirement) {
            app(ProfessionalExamService::class)->verifyRequirement($profile, $this->staff, $requirement->code, 'verified');
        }
        $attempt = app(ProfessionalExamService::class)->schedule($profile, $this->staff, ['scheduled_at' => now()->addDay(), 'place' => 'Sala ficticia']);
        app(ProfessionalExamService::class)->recordResult($attempt, $this->staff, 'absent', null, null);
        $this->expectException(ValidationException::class);
        app(ProfessionalExamService::class)->schedule($profile->fresh(), $this->staff, ['scheduled_at' => now()->addDays(2), 'place' => 'Sala ficticia']);
    }

    public function test_final_dossier_requires_approval_preserves_physical_sensitive_quantities_and_tracks_delivery(): void
    {
        $process = $this->process();
        $service = app(TitleFinalDossierService::class);
        try {
            $service->create($process, $this->staff);
            $this->fail();
        } catch (ValidationException) {
            $this->assertFalse($process->finalDossier()->exists());
        }
        $process->update(['result' => 'approved', 'current_stage' => TitleProcessStage::RESULT_RECORDED]);
        $dossier = $service->create($process->fresh(), $this->staff);
        $dni = $dossier->requirements->firstWhere('code', 'identity_copies');
        $photos = $dossier->requirements->firstWhere('code', 'photos');
        $birth = $dossier->requirements->firstWhere('code', 'birth_certificate');
        $this->assertTrue($dni->physical && $dni->sensitive);
        $this->assertSame(3, $dni->quantity);
        $this->assertSame(5, $photos->quantity);
        $this->assertTrue($birth->original && $birth->sensitive);
        foreach ($dossier->requirements as $requirement) {
            $service->reviewRequirement($requirement, $this->staff, $requirement->conditional ? 'not_applicable' : 'verified');
        }
        $service->conform($dossier, $this->staff);
        $service->submitForRegistration($dossier->fresh(), $this->staff);
        $service->register($dossier->fresh(), $this->staff, 'REG-FICTICIO-001', now()->toDateString());
        $service->markReady($dossier->fresh(), $this->staff, now()->toDateString(), now()->addDay()->toDateString());
        $service->deliver($dossier->fresh(), $this->staff);
        $this->assertSame('delivered', $dossier->fresh()->status);
        $this->assertSame(TitleProcessStage::DELIVERED, $process->fresh()->current_stage);
        $this->assertDatabaseHas('title_final_dossier_events', ['title_final_dossier_id' => $dossier->id, 'action' => 'delivered']);
    }

    private function process()
    {
        return app(TitleProcessService::class)->create($this->request, $this->applicant, TitleModality::PROFESSIONAL_EXAM, ['graduate_status' => 'declared']);
    }

    private function user(string $role): User
    {
        $model = Role::firstOrCreate(['name' => $role],['description' => 'Rol ficticio', 'active' => true]);

        return User::factory()->for($model)->create(['active' => true]);
    }
}
