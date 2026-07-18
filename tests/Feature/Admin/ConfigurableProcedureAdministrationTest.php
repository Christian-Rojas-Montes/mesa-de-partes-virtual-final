<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\ProcedureCategory;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedurePrerequisite;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableProcedureAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrator_can_access_configurable_catalogs(): void
    {
        $admin = $this->user('Administrador');
        $applicant = $this->user('Solicitante');
        $this->actingAs($admin)->get(route('admin.procedure-categories.index'))->assertOk();
        $this->actingAs($applicant)->get(route('admin.procedure-categories.index'))->assertForbidden();
        $this->actingAs($applicant)->post(route('admin.procedure-categories.store'), $this->categoryData())->assertForbidden();
    }

    public function test_administrator_creates_category_procedure_and_variant_with_audit(): void
    {
        $admin = $this->user('Administrador');
        $this->actingAs($admin)->post(route('admin.procedure-categories.store'), $this->categoryData())->assertRedirect();
        $category = ProcedureCategory::firstOrFail();
        $this->post(route('admin.procedure-types.store'), $this->procedureData(['procedure_category_id' => $category->id]))->assertRedirect();
        $type = ProcedureType::firstOrFail();
        $this->post(route('admin.procedure-types.variants.store', $type), $this->variantData())->assertRedirect();

        $this->assertDatabaseHas('procedure_variants', ['procedure_type_id' => $type->id, 'code' => 'INTERNA']);
        $this->assertGreaterThanOrEqual(3, AuditLog::query()->where('user_id', $admin->id)->count());
    }

    public function test_administrator_configures_physical_and_digital_requirements(): void
    {
        $type = ProcedureType::factory()->create();
        $admin = $this->user('Administrador');
        $this->actingAs($admin)->post(route('admin.procedure-types.requirements.store', $type), $this->requirementData(['name' => 'Original ficticio', 'type' => 'physical_document', 'requires_digital_file' => '0', 'requires_physical_submission' => '1', 'sensitive' => '1']))->assertRedirect();
        $this->post(route('admin.procedure-types.requirements.store', $type), $this->requirementData(['name' => 'Archivo ficticio', 'type' => 'digital_file', 'requires_digital_file' => '1', 'requires_physical_submission' => '0', 'allowed_formats_text' => 'application/pdf, image/jpeg']))->assertRedirect();

        $this->assertDatabaseHas('procedure_requirements', ['name' => 'Original ficticio', 'requires_physical_submission' => true, 'sensitive' => true]);
        $this->assertSame(['application/pdf', 'image/jpeg'], ProcedureRequirement::where('name', 'Archivo ficticio')->firstOrFail()->allowed_formats);
    }

    public function test_administrator_configures_dynamic_field_payment_window_and_prerequisite(): void
    {
        $admin = $this->user('Administrador');
        $previous = ProcedureType::factory()->create();
        $type = ProcedureType::factory()->create();
        $this->actingAs($admin)->put(route('admin.procedure-types.update', $type), $this->procedureData(['code' => $type->code, 'name' => $type->name, 'requires_payment' => '1', 'amount' => '35.50', 'available_from' => '2026-08-01 08:00', 'available_until' => '2026-08-31 17:00']))->assertRedirect();
        $this->post(route('admin.procedure-types.dynamic-fields.store', $type), ['key' => 'semester', 'type' => 'select', 'label' => 'Semestre', 'required' => '1', 'options_json' => '["I","II"]', 'visibility_conditions_json' => '[{"field":"entry_year","operator":"greater_than_or_equal","value":2007}]', 'sort_order' => 1])->assertRedirect();
        $this->post(route('admin.procedure-types.prerequisites.store', $type), ['required_procedure_type_id' => $previous->id, 'type' => 'approved_procedure', 'name' => 'Trámite previo', 'conditions_json' => '[{"field":"status","operator":"equals","value":"FINALIZADO"}]', 'required' => '1', 'sort_order' => 1])->assertRedirect();

        $type->refresh();
        $this->assertSame('35.50', $type->amount);
        $this->assertNotNull($type->available_from);
        $this->assertSame(['I', 'II'], ProcedureDynamicField::firstOrFail()->options);
        $this->assertSame($previous->id, ProcedurePrerequisite::firstOrFail()->required_procedure_type_id);
    }

    public function test_deactivation_is_logical_and_audited(): void
    {
        $admin = $this->user('Administrador');
        $type = ProcedureType::factory()->create(['active' => true]);
        $this->actingAs($admin)->patch(route('admin.procedure-types.toggle', $type))->assertRedirect();
        $this->assertDatabaseHas('procedure_types', ['id' => $type->id, 'active' => false]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ProcedureType::class, 'auditable_id' => $type->id, 'action' => 'deactivated']);
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        $admin = $this->user('Administrador');
        $type = ProcedureType::factory()->create();
        $this->actingAs($admin)->post(route('admin.procedure-types.dynamic-fields.store', $type), ['key' => 'INVALID KEY', 'type' => 'script', 'label' => '', 'required' => '1', 'options_json' => '{invalid}', 'sort_order' => -1])->assertSessionHasErrors(['key', 'type', 'label', 'options_json', 'sort_order']);
        $this->assertDatabaseCount('procedure_dynamic_fields', 0);
    }

    public function test_administrative_forms_include_csrf_protection(): void
    {
        $this->actingAs($this->user('Administrador'))
            ->get(route('admin.procedure-categories.create'))
            ->assertOk()
            ->assertSee('name="_token"', false);

        $this->assertContains('web', app('router')->getRoutes()->getByName('admin.procedure-categories.store')->gatherMiddleware());
    }

    public function test_all_configurable_catalog_screens_render_for_administrator(): void
    {
        $admin = $this->user('Administrador');
        $type = ProcedureType::factory()->create();

        $routes = [
            route('admin.procedure-categories.index'), route('admin.procedure-categories.create'),
            route('admin.presentation-modalities.index'), route('admin.presentation-modalities.create'),
            route('admin.procedure-types.index'), route('admin.procedure-types.create'),
            route('admin.procedure-types.variants.index', $type), route('admin.procedure-types.variants.create', $type),
            route('admin.procedure-types.requirements.index', $type), route('admin.procedure-types.requirements.create', $type),
            route('admin.procedure-types.dynamic-fields.index', $type), route('admin.procedure-types.dynamic-fields.create', $type),
            route('admin.procedure-types.prerequisites.index', $type), route('admin.procedure-types.prerequisites.create', $type),
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get($route)->assertOk();
        }
    }

    private function user(string $roleName): User
    {
        $role = Role::factory()->create(['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function categoryData(): array
    {
        return ['code' => 'REG', 'name' => 'Regulares ficticios', 'description' => 'Categoría ficticia.', 'sort_order' => 1];
    }

    private function procedureData(array $overrides = []): array
    {
        return ['code' => 'TR-FICT', 'name' => 'Trámite ficticio configurable', 'description' => 'Descripción ficticia.', 'instructions' => 'Instrucciones ficticias.', 'attention_days' => 10, 'sort_order' => 1, 'reception_open' => '1', 'allows_digital_registration' => '1', 'requires_physical_delivery' => '0', 'requires_payment' => '0', 'currency' => 'PEN', ...$overrides];
    }

    private function variantData(): array
    {
        return ['code' => 'INTERNA', 'name' => 'Variante interna ficticia', 'description' => 'Descripción.', 'conditions_json' => '[{"field":"origin","operator":"equals","value":"institute"}]', 'sort_order' => 1, 'reception_open' => '1', 'allows_digital_registration' => '1', 'requires_physical_delivery' => '0', 'requires_payment' => '0', 'currency' => 'PEN'];
    }

    private function requirementData(array $overrides = []): array
    {
        return ['name' => 'Requisito ficticio', 'description' => 'Descripción ficticia.', 'type' => 'digital_file', 'required' => '1', 'sort_order' => 1, 'copy_count' => 1, 'requires_original' => '0', 'requires_simple_copy' => '0', 'requires_authenticated_copy' => '0', 'requires_legalized_copy' => '0', 'requires_endorsement' => '0', 'requires_issue_date' => '0', 'requires_physical_submission' => '0', 'requires_digital_file' => '1', 'sensitive' => '0', ...$overrides];
    }
}
