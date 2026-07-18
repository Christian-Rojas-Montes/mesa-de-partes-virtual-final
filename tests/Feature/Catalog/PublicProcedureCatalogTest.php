<?php

namespace Tests\Feature\Catalog;

use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProcedureCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_shows_active_procedures_and_accessible_status(): void
    {
        $type = $this->procedure();
        $this->get(route('catalog.index'))->assertOk()->assertSee($type->name)->assertSee('Estado: Disponible')->assertSee('<article', false)->assertSee('<h1>', false)->assertSee('for="buscar"', false);
    }

    public function test_closed_procedure_is_informative_but_cannot_start(): void
    {
        $type = $this->procedure(['reception_open' => false, 'unavailable_message' => 'Recepción ficticia cerrada.']);
        $this->get(route('catalog.show', $type->code))->assertOk()->assertSee('Cerrado')->assertSee('Recepción ficticia cerrada.')->assertDontSee('Continuar al formulario actual');
        $this->post(route('catalog.start', $type->code))->assertSessionHasErrors('procedure');
    }

    public function test_inactive_procedure_is_hidden_and_protected_by_url(): void
    {
        $type = $this->procedure(['active' => false]);
        $this->get(route('catalog.index'))->assertDontSee($type->name);
        $this->get(route('catalog.show', $type->code))->assertNotFound();
        $this->post(route('catalog.start', $type->code))->assertNotFound();
    }

    public function test_detail_shows_requirements_cost_modality_and_sensitive_warning(): void
    {
        $type = $this->procedure(['requires_payment' => true, 'amount' => 25.50, 'instructions' => 'Instrucción ficticia.']);
        ProcedureRequirement::factory()->for($type)->create(['name' => 'Documento médico ficticio', 'sensitive' => true, 'requires_physical_submission' => true, 'requires_original' => true, 'copy_count' => 2]);
        $this->get(route('catalog.show', $type->code))->assertOk()->assertSee('PEN 25.50')->assertSee('Documento médico ficticio')->assertSee('Privacidad')->assertSee('2 ejemplar(es)')->assertSee('Instrucción ficticia.');
    }

    public function test_catalog_filters_by_category_modality_audience_and_name(): void
    {
        $category = ProcedureCategory::factory()->create(['code' => 'EGR', 'name' => 'Egresados ficticios']);
        $modality = PresentationModality::factory()->create(['code' => 'digital', 'name' => 'Digital']);
        $match = $this->procedure(['name' => 'Constancia ficticia especial', 'procedure_category_id' => $category->id, 'presentation_modality_id' => $modality->id]);
        $other = $this->procedure(['name' => 'Solicitud distinta']);
        $this->get(route('catalog.index', ['buscar' => 'Constancia', 'categoria' => 'EGR', 'modalidad' => 'digital', 'publico' => 'Egresados']))->assertOk()->assertSee($match->name)->assertDontSee($other->name);
    }

    public function test_variant_is_selected_only_when_declared_conditions_match(): void
    {
        $type = $this->procedure();
        $internal = ProcedureVariant::factory()->for($type)->create(['code' => 'INTERNA', 'name' => 'Interna ficticia', 'conditions' => [['field' => 'origin', 'operator' => 'equals', 'value' => 'institute']]]);
        ProcedureVariant::factory()->for($type)->create(['code' => 'EXTERNA', 'conditions' => [['field' => 'origin', 'operator' => 'equals', 'value' => 'other']]]);
        $this->post(route('catalog.select', $type->code), ['variant_code' => 'INTERNA', 'answers' => ['origin' => 'institute']])->assertRedirect(route('catalog.show', ['procedureType' => $type->code, 'variante' => $internal->code]));
        $this->get(route('catalog.show', ['procedureType' => $type->code, 'variante' => 'INTERNA']))->assertSee('Base: información declarada por el usuario');
        $this->post(route('catalog.select', $type->code), ['variant_code' => 'INTERNA', 'answers' => ['origin' => 'other']])->assertSessionHasErrors('answers');
    }

    public function test_guest_returns_to_selected_procedure_after_login(): void
    {
        $type = $this->procedure();
        $this->post(route('catalog.start', $type->code))->assertRedirect(route('login'));
        $applicant = $this->applicant();
        $this->post(route('login'), ['email' => $applicant->email, 'password' => 'password'])->assertRedirect(route('catalog.resume', $type->code));
        $this->get(route('catalog.resume', $type->code))->assertRedirect(route('applicant.procedure-requests.create', ['tipo' => $type->id]));
    }

    public function test_authenticated_non_applicant_cannot_resume_procedure(): void
    {
        $type = $this->procedure();
        $role = Role::factory()->create(['name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user)->get(route('catalog.resume', $type->code))->assertForbidden();
    }

    private function procedure(array $overrides = []): ProcedureType
    {
        return ProcedureType::factory()->create(['active' => true, 'reception_open' => true, 'allows_digital_registration' => true, 'requires_physical_delivery' => false, ...$overrides]);
    }

    private function applicant(): User
    {
        $role = Role::factory()->create(['name' => 'Solicitante']);
        return User::factory()->create(['role_id' => $role->id]);
    }
}
