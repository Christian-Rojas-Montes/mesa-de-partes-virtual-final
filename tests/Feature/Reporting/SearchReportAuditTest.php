<?php

namespace Tests\Feature\Reporting;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchReportAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['REGISTRADO', 'Registrado'], ['DERIVADO', 'Derivado'],
            ['EN_ATENCION', 'En atención'], ['ATENDIDO', 'Atendido'],
            ['FINALIZADO', 'Finalizado'], ['RECHAZADO', 'Rechazado'],
        ] as $index => [$code, $name]) {
            Status::query()->create([
                'code' => $code, 'name' => $name, 'description' => 'Estado ficticio '.$name.'.',
                'sort_order' => $index + 1, 'active' => true,
            ]);
        }
    }

    public function test_search_applies_all_filters_and_returns_paginated_results(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante', null, [
            'first_name' => 'Lucía Ficticia', 'last_name' => 'Prueba Segura', 'document_number' => 'DOC-FICT-9001',
        ]);
        $area = Area::factory()->create(['name' => 'Área objetivo ficticia']);
        $manager = $this->userWithRole('Responsable de área', $area);
        $type = ProcedureType::factory()->create(['name' => 'Trámite objetivo ficticio']);
        $target = $this->request($applicant, $type, 'DERIVADO', 'MPV-2026-008888', '2026-07-10 09:00:00');
        $this->derive($target, $frontDesk, $area);
        $decoy = $this->request($this->userWithRole('Solicitante'), ProcedureType::factory()->create(), 'REGISTRADO', 'MPV-2026-001111', '2026-06-01 09:00:00');

        $response = $this->actingAs($frontDesk)->get(route('search.index', [
            'codigo' => '008888', 'documento' => 'FICT-9001', 'nombre' => 'Lucía Prueba',
            'estado' => $target->status_id, 'tramite' => $type->id, 'area' => $area->id,
            'desde' => '2026-07-01', 'hasta' => '2026-07-31', 'responsable' => $manager->first_name.' '.$manager->last_name,
        ]))->assertOk()->assertSee($target->tracking_code)->assertDontSee($decoy->tracking_code);

        $this->assertCount(1, $response->viewData('procedureRequests')->items());
        $this->actingAs($frontDesk)->get(route('search.index', ['desde' => '2026-07-31', 'hasta' => '2026-07-01']))
            ->assertSessionHasErrors('hasta');
    }

    public function test_search_scope_is_enforced_for_each_role_and_direct_detail_access(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $administrator = $this->userWithRole('Administrador');
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $applicant = $this->userWithRole('Solicitante');
        $otherApplicant = $this->userWithRole('Solicitante');
        $type = ProcedureType::factory()->create();
        $own = $this->request($applicant, $type, 'DERIVADO', 'MPV-2026-002001');
        $foreign = $this->request($otherApplicant, $type, 'DERIVADO', 'MPV-2026-002002');
        $otherAreaRequest = $this->request($otherApplicant, $type, 'DERIVADO', 'MPV-2026-002003');
        $this->derive($own, $frontDesk, $area);
        $this->derive($foreign, $frontDesk, $area);
        $this->derive($otherAreaRequest, $frontDesk, $otherArea);

        $this->actingAs($applicant)->get(route('search.index'))->assertOk()
            ->assertSee($own->tracking_code)->assertDontSee($foreign->tracking_code);
        $this->get(route('search.show', $foreign))->assertNotFound();

        $this->actingAs($manager)->get(route('search.index'))->assertOk()
            ->assertSee($own->tracking_code)->assertSee($foreign->tracking_code)->assertDontSee($otherAreaRequest->tracking_code);
        $this->get(route('search.show', $otherAreaRequest))->assertNotFound();

        $this->actingAs($frontDesk)->get(route('search.index'))->assertOk()->assertSee($otherAreaRequest->tracking_code);
        $this->actingAs($administrator)->get(route('search.index'))->assertOk()->assertSee($otherAreaRequest->tracking_code);
    }

    public function test_reports_return_grouped_counts_periods_pending_and_average_response_time(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $administrator = $this->userWithRole('Administrador');
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $area = Area::factory()->create(['name' => 'Área reporte ficticia']);
        $type = ProcedureType::factory()->create(['name' => 'Trámite reporte ficticio']);
        $pending = $this->request($applicant, $type, 'DERIVADO', 'MPV-2026-003001', '2026-07-01 10:00:00');
        $attended = $this->request($applicant, $type, 'ATENDIDO', 'MPV-2026-003002', '2026-07-02 10:00:00');
        $this->derive($pending, $frontDesk, $area);
        $this->derive($attended, $frontDesk, $area);
        $attended->response()->create([
            'user_id' => $frontDesk->id, 'summary' => 'Respuesta ficticia.', 'responded_at' => '2026-07-04 10:00:00',
            'disk' => 'private', 'path' => 'responses/report-ficticio.pdf', 'stored_name' => 'report-ficticio.pdf',
            'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100, 'checksum_sha256' => str_repeat('a', 64),
        ]);

        $response = $this->actingAs($administrator)->get(route('reports.index', ['desde' => '2026-07-01', 'hasta' => '2026-07-31']))
            ->assertOk()->assertSee('Área reporte ficticia')->assertSee('Trámite reporte ficticio')->assertSee('48.0 horas');
        $report = $response->viewData('report');
        $this->assertSame(2, (int) $report['summary']->total);
        $this->assertSame(1, (int) $report['summary']->pending);
        $this->assertSame(1, (int) $report['summary']->attended);
        $this->assertCount(1, $report['byPeriod']);
    }

    public function test_report_scope_prevents_area_manager_and_applicant_from_counting_foreign_requests(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $applicant = $this->userWithRole('Solicitante');
        $otherApplicant = $this->userWithRole('Solicitante');
        $type = ProcedureType::factory()->create();
        $own = $this->request($applicant, $type, 'DERIVADO', 'MPV-2026-004001');
        $sameAreaForeign = $this->request($otherApplicant, $type, 'DERIVADO', 'MPV-2026-004002');
        $other = $this->request($otherApplicant, $type, 'DERIVADO', 'MPV-2026-004003');
        $this->derive($own, $frontDesk, $area);
        $this->derive($sameAreaForeign, $frontDesk, $area);
        $this->derive($other, $frontDesk, $otherArea);

        $applicantReport = $this->actingAs($applicant)->get(route('reports.index'))->assertOk()->viewData('report');
        $this->assertSame(1, (int) $applicantReport['summary']->total);
        $areaReport = $this->actingAs($manager)->get(route('reports.index'))->assertOk()->viewData('report');
        $this->assertSame(2, (int) $areaReport['summary']->total);
    }

    public function test_user_with_inactive_role_cannot_access_search_or_reports(): void
    {
        $user = $this->userWithRole('Mesa de Partes');
        $user->role()->update(['active' => false]);

        $this->actingAs($user)->get(route('search.index'))->assertRedirect(route('login'));
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_csv_export_respects_scope_masks_document_and_is_audited(): void
    {
        $applicant = $this->userWithRole('Solicitante', null, ['document_number' => '87654321', 'academic_program' => 'Programa ficticio']);
        $other = $this->userWithRole('Solicitante', null, ['document_number' => '11223344']);
        $type = ProcedureType::factory()->create(['name' => 'Trámite exportable ficticio']);
        $own = $this->request($applicant, $type, 'REGISTRADO', 'MPV-2026-CSV001');
        $foreign = $this->request($other, $type, 'REGISTRADO', 'MPV-2026-CSV002');

        $response = $this->actingAs($applicant)->get(route('search.export'));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString($own->tracking_code, $content);
        $this->assertStringNotContainsString($foreign->tracking_code, $content);
        $this->assertStringContainsString('****4321', $content);
        $this->assertStringNotContainsString('87654321', $content);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $applicant->id, 'action' => 'procedure_search_exported']);
    }

    public function test_audit_is_admin_only_filterable_paginated_read_only_and_sanitized(): void
    {
        $administrator = $this->userWithRole('Administrador');
        $actor = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $target = $this->audit($actor, 'derived', ProcedureRequest::class, 501, '2026-07-10 10:00:00', [
            'to_area_id' => 8, 'password' => 'NO-MOSTRAR', 'token' => 'NO-MOSTRAR-TOKEN', 'path' => 'privado/secreto.pdf',
        ]);
        $this->audit($applicant, 'submitted', ProcedureRequest::class, 502, '2026-06-01 10:00:00');
        foreach (range(1, 21) as $id) {
            $this->audit($actor, 'updated', Area::class, 600 + $id, '2026-05-01 10:00:00');
        }

        $this->actingAs($applicant)->get(route('admin.audit-logs.index'))->assertForbidden();
        $response = $this->actingAs($administrator)->get(route('admin.audit-logs.index', [
            'usuario' => $actor->email, 'accion' => 'derived', 'entidad' => ProcedureRequest::class,
            'desde' => '2026-07-01', 'hasta' => '2026-07-31',
        ]))->assertOk()->assertSee('Expediente derivado')->assertSee('Solicitud #501')
            ->assertSee('To Area Id')->assertDontSee('NO-MOSTRAR')->assertDontSee('NO-MOSTRAR-TOKEN')
            ->assertDontSee('Editar')->assertDontSee('Eliminar');
        $this->assertCount(1, $response->viewData('logs')->items());
        $storedDetails = json_encode($target->fresh()->details);
        $this->assertStringNotContainsString('NO-MOSTRAR', $storedDetails);
        $this->assertStringNotContainsString('privado/secreto.pdf', $storedDetails);

        $paginated = $this->get(route('admin.audit-logs.index', ['accion' => 'updated']))->assertOk()->viewData('logs');
        $this->assertCount(20, $paginated->items());
        $this->assertTrue($paginated->hasMorePages());
        $this->delete('/panel/administracion/auditoria/'.$target->id)->assertNotFound();
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $roleName, ?Area $area = null, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['description' => 'Rol ficticio.', 'active' => true]);

        return User::factory()->for($role)->create([
            'area_id' => $area?->id, 'email' => fake()->unique()->userName().'@example.test', 'active' => true, ...$attributes,
        ]);
    }

    private function request(User $applicant, ProcedureType $type, string $status, string $code, string $date = '2026-07-10 09:00:00'): ProcedureRequest
    {
        return ProcedureRequest::factory()->create([
            'user_id' => $applicant->id, 'procedure_type_id' => $type->id,
            'status_id' => Status::where('code', $status)->value('id'), 'tracking_code' => $code, 'submitted_at' => $date,
        ]);
    }

    private function derive(ProcedureRequest $request, User $actor, Area $area): void
    {
        $request->derivations()->create([
            'to_area_id' => $area->id, 'user_id' => $actor->id, 'reason' => null, 'derived_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $details */
    private function audit(User $user, string $action, string $type, int $id, string $date, array $details = []): AuditLog
    {
        $log = AuditLog::query()->create([
            'user_id' => $user->id, 'action' => $action, 'auditable_type' => $type,
            'auditable_id' => $id, 'details' => $details,
        ]);
        $log->created_at = $date;
        $log->save();

        return $log;
    }
}
