<?php

namespace Tests\Feature\Applicant;

use App\Enums\DynamicFieldType;
use App\Enums\RequirementType;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DynamicProcedureRequestRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateDocumentStorage::DISK);
        Status::factory()->create(['code' => 'REGISTRADO', 'active' => true]);
    }

    public function test_simple_and_conditional_fields_are_validated_on_server(): void
    {
        [$user, $type] = $this->context();
        ProcedureDynamicField::factory()->for($type)->create(['key' => 'email_contacto', 'type' => DynamicFieldType::EMAIL, 'label' => 'Correo de contacto', 'required' => true]);
        ProcedureDynamicField::factory()->for($type)->create(['key' => 'codigo_egresado', 'label' => 'Código de egresado', 'required' => true, 'visibility_conditions' => [['field' => 'condicion', 'operator' => 'equals', 'value' => 'egresado']]]);
        $this->actingAs($user)->get(route('applicant.procedure-requests.create', ['tipo' => $type->id]))->assertOk()->assertSee('Correo de contacto');
        $this->post(route('applicant.procedure-requests.store'), $this->payload($type, ['email_contacto' => 'incorrecto', 'condicion' => 'egresado']))->assertSessionHasErrors(['responses.email_contacto', 'responses.codigo_egresado']);
    }

    public function test_variant_and_only_its_requirement_are_persisted(): void
    {
        [$user, $type] = $this->context();
        $variant = ProcedureVariant::factory()->for($type)->create(['conditions' => [['field' => 'condicion', 'operator' => 'equals', 'value' => 'egresado']]]);
        $other = ProcedureVariant::factory()->for($type)->create();
        $required = ProcedureRequirement::factory()->for($type)->for($variant, 'variant')->create();
        ProcedureRequirement::factory()->for($type)->for($other, 'variant')->create();
        $this->actingAs($user)->post(route('applicant.procedure-requests.store'), $this->payload($type, [], ['procedure_variant_id' => $variant->id, 'eligibility' => ['condicion' => 'egresado'], 'documents' => [$required->id => $this->pdf()]]))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $this->assertSame($variant->id, $request->procedure_variant_id);
        $this->assertSame($variant->name, $request->configuration_snapshot['variant']['name']);
        $this->assertCount(1, $request->configuration_snapshot['requirements']);
    }

    public function test_physical_and_optional_requirements_do_not_force_upload(): void
    {
        [$user, $type] = $this->context();
        ProcedureRequirement::factory()->for($type)->create(['type' => RequirementType::PHYSICAL_DOCUMENT, 'required' => true, 'requires_digital_file' => false, 'requires_physical_submission' => true]);
        ProcedureRequirement::factory()->for($type)->create(['required' => false, 'requires_digital_file' => true]);
        $this->actingAs($user)->post(route('applicant.procedure-requests.store'), $this->payload($type))->assertRedirect();
        $this->assertDatabaseCount('request_documents', 0);
        $this->assertContains(true, collect(ProcedureRequest::firstOrFail()->configuration_snapshot['requirements'])->pluck('physical')->all());
    }

    public function test_configured_file_format_is_enforced(): void
    {
        [$user, $type] = $this->context();
        $requirement = ProcedureRequirement::factory()->for($type)->create(['allowed_formats' => ['application/pdf'], 'max_file_size_kb' => 10]);
        $png = UploadedFile::fake()->createWithContent('imagen.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $this->actingAs($user)->post(route('applicant.procedure-requests.store'), $this->payload($type, [], ['documents' => [$requirement->id => $png]]))->assertSessionHasErrors("documents.{$requirement->id}");
        $this->assertDatabaseCount('procedure_requests', 0);
    }

    public function test_closed_procedure_and_manipulated_variant_are_rejected(): void
    {
        [$user, $type] = $this->context(['reception_open' => false]);
        $this->actingAs($user)->post(route('applicant.procedure-requests.store'), $this->payload($type))->assertSessionHasErrors('procedure_type_id');
        $type->update(['reception_open' => true]);
        $foreign = ProcedureVariant::factory()->for(ProcedureType::factory()->create())->create();
        $this->post(route('applicant.procedure-requests.store'), $this->payload($type, [], ['procedure_variant_id' => $foreign->id]))->assertSessionHasErrors('procedure_type_id');
        $this->assertDatabaseCount('procedure_requests', 0);
    }

    public function test_browser_amount_is_ignored_and_server_snapshot_is_historical(): void
    {
        [$user, $type] = $this->context(['requires_payment' => true, 'amount' => 125.50, 'currency' => 'PEN']);
        ProcedureDynamicField::factory()->for($type)->create(['key' => 'dato', 'required' => false]);
        $this->actingAs($user)->post(route('applicant.procedure-requests.store'), $this->payload($type, ['dato' => 'valor'], ['amount' => 0.01, 'documents' => ['general' => $this->pdf()]]))->assertRedirect();
        $request = ProcedureRequest::firstOrFail();
        $this->assertSame('125.50', $request->configuration_snapshot['amount']['value']);
        $original = $request->configuration_snapshot['procedure']['name'];
        $type->update(['name' => 'Nombre nuevo ficticio']);
        $this->assertSame($original, $request->fresh()->configuration_snapshot['procedure']['name']);
    }

    public function test_legacy_request_without_snapshot_remains_readable(): void
    {
        [$user, $type] = $this->context();
        $legacy = ProcedureRequest::factory()->for($user)->for($type)->create(['status_id' => Status::first()->id, 'configuration_snapshot' => null, 'dynamic_responses' => null]);
        $this->actingAs($user)->get(route('applicant.procedure-requests.show', $legacy))->assertOk();
    }

    private function context(array $attributes = []): array
    {
        $role = Role::firstOrCreate(['name' => 'Solicitante'], ['description' => 'Rol de prueba', 'active' => true]);
        $user = User::factory()->for($role)->create(['active' => true, 'area_id' => null]);
        $type = ProcedureType::factory()->create([...$attributes, 'active' => true, 'allows_digital_registration' => true]);

        return [$user, $type];
    }

    private function payload(ProcedureType $type, array $responses = [], array $extra = []): array
    {
        return ['procedure_type_id' => $type->id, 'subject' => 'Solicitud ficticia', 'description' => 'Descripción ficticia de prueba.', 'responses' => $responses, 'confirmation' => '1', ...$extra];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('documento.pdf', "%PDF-1.4\n%%EOF");
    }
}
