<?php

namespace Tests\Feature\Catalog;

use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\InstitutionalCatalogSyncService;
use App\Services\ProcedurePrerequisiteValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConvalidationCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstitutionalCatalogSyncService::class)->synchronize(true);
    }

    public function test_catalog_has_year_ranges_configurable_window_terms_and_amounts(): void
    {
        $constancy = ProcedureType::where('code', 'CONV_CONSTANCIA_NOTAS')->firstOrFail();
        $report = ProcedureType::where('code', 'CONV_REPORTE_NOTAS')->firstOrFail();
        $this->assertSame('1986.0000', $constancy->dynamicFields()->where('key', 'anio_ingreso')->value('min_value'));
        $this->assertSame('2006.0000', $constancy->dynamicFields()->where('key', 'anio_ingreso')->value('max_value'));
        $this->assertSame('2007.0000', $report->dynamicFields()->where('key', 'anio_ingreso')->value('min_value'));
        $this->assertSame(3, $report->attention_days);
        $this->assertFalse($report->reception_open);
        $this->assertSame('20.00', $constancy->amount);
        $this->assertSame('5.00', $report->amount);
        $this->assertSame('50.00', ProcedureType::where('code', 'CONV_INTERNA')->value('amount'));
        $this->assertSame('60.00', ProcedureType::where('code', 'CONV_EXTERNA')->value('amount'));
        $this->get(route('catalog.show', $constancy->code))->assertOk()->assertSee('1986 a 2006')->assertSee('fechas programadas');
    }

    public function test_internal_convalidation_requires_the_previous_grade_procedure_for_the_declared_year(): void
    {
        $user = $this->applicant();
        $internal = ProcedureType::where('code', 'CONV_INTERNA')->firstOrFail();
        $validator = app(ProcedurePrerequisiteValidator::class);
        $this->assertStringContainsString('Constancia de notas previa', $validator->errors($user, $internal, ['anio_ingreso_anterior' => 2000])->first());
        $this->assertStringContainsString('Reporte de notas previo', $validator->errors($user, $internal, ['anio_ingreso_anterior' => 2007])->first());

        $finished = Status::factory()->create(['code' => 'FINALIZADO', 'name' => 'Finalizado']);
        ProcedureRequest::factory()->create(['user_id' => $user->id, 'procedure_type_id' => ProcedureType::where('code', 'CONV_CONSTANCIA_NOTAS')->value('id'), 'status_id' => $finished->id]);
        $this->assertTrue($validator->errors($user, $internal, ['anio_ingreso_anterior' => 2000])->isEmpty());
    }

    public function test_external_convalidation_is_hybrid_and_keeps_originals_pending(): void
    {
        $external = ProcedureType::where('code', 'CONV_EXTERNA')->with(['presentationModality', 'requirements'])->firstOrFail();
        $this->assertSame('hybrid', $external->presentationModality->code->value);
        $this->assertTrue($external->requires_physical_delivery);
        $certificate = $external->requirements->firstWhere('name', 'Certificados originales de estudios');
        $syllabi = $external->requirements->firstWhere('name', 'Sílabos visados de las unidades a convalidar');
        $this->assertTrue($certificate->requires_original);
        $this->assertTrue($certificate->requires_physical_submission);
        $this->assertTrue($certificate->requires_digital_file);
        $this->assertTrue($certificate->sensitive);
        $this->assertTrue($syllabi->requires_endorsement);
        $this->assertTrue($syllabi->sensitive);
    }

    public function test_closed_window_blocks_new_registration_but_keeps_existing_tracking(): void
    {
        $user = $this->applicant();
        $type = ProcedureType::where('code', 'CONV_INTERNA')->firstOrFail();
        $status = Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado']);
        $request = ProcedureRequest::factory()->create(['user_id' => $user->id, 'procedure_type_id' => $type->id, 'status_id' => $status->id]);

        $this->actingAs($user)->post(route('catalog.start', $type->code))->assertSessionHasErrors();
        $this->get(route('applicant.procedure-requests.show', $request))->assertOk()->assertSee($request->tracking_code);
    }

    public function test_only_authorized_staff_assigns_academic_number_and_private_files_remain_restricted(): void
    {
        Storage::fake('private');
        $owner = $this->applicant();
        $other = $this->applicant();
        $staff = $this->user('Mesa de Partes');
        $type = ProcedureType::where('code', 'CONV_EXTERNA')->firstOrFail();
        $status = Status::factory()->create(['code' => 'EN_REVISION', 'name' => 'En revisión']);
        $request = ProcedureRequest::factory()->create(['user_id' => $owner->id, 'procedure_type_id' => $type->id, 'status_id' => $status->id]);
        Storage::disk('private')->put('requests/certificado.pdf', '%PDF-1.4 privado');
        $document = RequestDocument::create(['procedure_request_id' => $request->id, 'disk' => 'private', 'path' => 'requests/certificado.pdf', 'stored_name' => 'certificado.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 16, 'checksum_sha256' => str_repeat('a', 64)]);

        $this->actingAs($other)->post(route('front-desk.reviews.academic-file.assign', $request), ['academic_file_number' => 'ACAD-FICT-001'])->assertForbidden();
        $this->actingAs($staff)->post(route('front-desk.reviews.academic-file.assign', $request), ['academic_file_number' => 'ACAD-FICT-001'])->assertRedirect();
        $this->assertDatabaseHas('procedure_requests', ['id' => $request->id, 'academic_file_number' => 'ACAD-FICT-001']);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'academic_file_assigned']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'academic_file_assigned']);
        $this->actingAs($other)->get(route('applicant.procedure-requests.documents.download', [$request, $document]))->assertForbidden();
    }

    private function applicant(): User
    {
        return $this->user('Solicitante');
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['description' => 'Rol ficticio', 'active' => true]);

        return User::factory()->for($role)->create(['active' => true]);
    }
}
