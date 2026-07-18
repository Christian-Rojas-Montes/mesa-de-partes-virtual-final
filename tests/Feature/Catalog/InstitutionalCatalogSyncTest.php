<?php

namespace Tests\Feature\Catalog;

use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Services\InstitutionalCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_writing_and_requires_an_explicit_mode(): void
    {
        $this->artisan('catalog:sync-institutional --dry-run --catalog-version=2026.07.3')
            ->expectsOutputToContain('Altas:')->assertSuccessful();
        $this->assertDatabaseCount('procedure_types', 0);
        $this->artisan('catalog:sync-institutional')->assertExitCode(2);
    }

    public function test_apply_is_idempotent_and_preserves_manual_records(): void
    {
        $manual = ProcedureType::factory()->create(['code' => 'MANUAL_LOCAL', 'name' => 'Trámite administrativo manual']);
        $this->artisan('catalog:sync-institutional --apply')->assertSuccessful();
        $count = ProcedureType::count();
        $this->artisan('catalog:sync-institutional --apply')->expectsOutputToContain('Cambios: 0')->assertSuccessful();
        $this->assertSame($count, ProcedureType::count());
        $this->assertDatabaseHas('procedure_types', ['id' => $manual->id, 'name' => 'Trámite administrativo manual']);
        $this->assertDatabaseMissing('procedure_types', ['name' => 'Convalidación']);
        $this->assertDatabaseHas('procedure_types', ['code' => 'TITLE_PROF_TECH', 'name' => 'Título Profesional Técnico']);
        $this->assertDatabaseHas('institutional_catalog_versions', ['version' => '2026.07.3']);
    }

    public function test_amounts_conditions_sensitive_requirements_variants_and_public_catalog_are_loaded(): void
    {
        app(InstitutionalCatalogSyncService::class)->synchronize(true);
        $this->assertSame('20.00', ProcedureType::where('code', 'REG_CONSTANCIA_ESTUDIOS')->value('amount'));
        $this->assertSame('5.00', ProcedureType::where('code', 'REG_REPORTE_NOTAS')->value('amount'));
        $this->assertSame('25.00', ProcedureType::where('code', 'EGR_SILABOS')->value('amount'));
        $this->assertDatabaseHas('procedure_variants', ['code' => 'NOTA_10_12', 'amount' => 120]);
        $variant = ProcedureVariant::where('code', 'NOTA_MENOR_10')->firstOrFail();
        $this->assertSame('less_than', $variant->conditions[0]['operator']);
        $this->assertTrue(ProcedureRequirement::where('name', 'Sentencia judicial')->firstOrFail()->sensitive);
        $this->assertTrue(ProcedureRequirement::where('name', 'Certificado original de trabajo o salud')->firstOrFail()->sensitive);

        $this->get(route('catalog.index'))->assertOk()->assertSee('Constancia de estudios');
        $this->get(route('catalog.index', ['buscar' => 'Sílabos']))->assertOk()->assertSee('Sílabos');
    }

    public function test_local_modification_is_reported_and_not_overwritten(): void
    {
        $service = app(InstitutionalCatalogSyncService::class);
        $service->synchronize(true);
        $procedure = ProcedureType::where('code', 'REG_CONSTANCIA_ESTUDIOS')->firstOrFail();
        $procedure->update(['name' => 'Nombre local protegido']);
        $report = $service->synchronize(true);
        $this->assertNotEmpty($report['conflicts']);
        $this->assertSame('Nombre local protegido', $procedure->fresh()->name);
    }
}
