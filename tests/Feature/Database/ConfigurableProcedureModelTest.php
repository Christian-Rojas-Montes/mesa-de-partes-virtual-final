<?php

namespace Tests\Feature\Database;

use App\Enums\DynamicFieldType;
use App\Enums\PaymentTiming;
use App\Enums\PrerequisiteType;
use App\Enums\RequirementType;
use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedurePrerequisite;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ConfigurableProcedureModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_procedure_variant_and_requirement_relations_are_available(): void
    {
        $category = ProcedureCategory::factory()->create();
        $modality = PresentationModality::factory()->create(['code' => 'hybrid']);
        $procedureType = ProcedureType::factory()->create([
            'procedure_category_id' => $category->id,
            'presentation_modality_id' => $modality->id,
        ]);
        $variant = ProcedureVariant::factory()->for($procedureType)->create([
            'presentation_modality_id' => $modality->id,
        ]);
        $requirement = ProcedureRequirement::factory()->for($procedureType)->create([
            'procedure_variant_id' => $variant->id,
        ]);

        $this->assertTrue($category->procedureTypes->contains($procedureType));
        $this->assertTrue($procedureType->variants->contains($variant));
        $this->assertTrue($variant->requirements->contains($requirement));
        $this->assertTrue($procedureType->presentationModality->is($modality));
    }

    public function test_requirement_supports_digital_physical_and_sensitive_configuration(): void
    {
        $physical = ProcedureRequirement::factory()->create([
            'type' => RequirementType::PHYSICAL_DOCUMENT,
            'requires_digital_file' => false,
            'requires_physical_submission' => true,
            'requires_original' => true,
            'copy_count' => 2,
            'sensitive' => true,
        ]);
        $digital = ProcedureRequirement::factory()->create([
            'type' => RequirementType::DIGITAL_FILE,
            'allowed_formats' => ['pdf', 'jpg'],
            'max_file_size_kb' => 5120,
        ]);

        $this->assertSame(RequirementType::PHYSICAL_DOCUMENT, $physical->type);
        $this->assertTrue($physical->requires_physical_submission);
        $this->assertTrue($physical->sensitive);
        $this->assertSame(['pdf', 'jpg'], $digital->allowed_formats);
    }

    public function test_procedure_supports_availability_payment_and_dynamic_fields(): void
    {
        $procedureType = ProcedureType::factory()->create([
            'available_from' => '2026-08-01 08:00:00',
            'available_until' => '2026-08-31 17:00:00',
            'academic_period' => '2026-II',
            'requires_payment' => true,
            'amount' => 45.50,
            'currency' => 'PEN',
            'payment_timing' => PaymentTiming::BEFORE,
        ]);
        $field = ProcedureDynamicField::factory()->for($procedureType)->create([
            'key' => 'entry_year',
            'type' => DynamicFieldType::NUMBER,
            'min_value' => 1986,
            'max_value' => 2100,
            'visibility_conditions' => [[
                'field' => 'applicant_origin',
                'operator' => 'equals',
                'value' => 'institute',
            ]],
        ]);

        $this->assertTrue($procedureType->requires_payment);
        $this->assertSame('45.50', $procedureType->amount);
        $this->assertSame(PaymentTiming::BEFORE, $procedureType->payment_timing);
        $this->assertTrue($procedureType->dynamicFields->contains($field));
        $this->assertSame('applicant_origin', $field->visibility_conditions[0]['field']);
    }

    public function test_procedure_can_depend_on_another_procedure(): void
    {
        $requiredProcedure = ProcedureType::factory()->create();
        $procedureType = ProcedureType::factory()->create();
        $prerequisite = ProcedurePrerequisite::factory()->for($procedureType)->create([
            'required_procedure_type_id' => $requiredProcedure->id,
            'type' => PrerequisiteType::APPROVED_PROCEDURE,
        ]);

        $this->assertTrue($procedureType->prerequisites->contains($prerequisite));
        $this->assertTrue($prerequisite->requiredProcedureType->is($requiredProcedure));
    }

    public function test_structured_conditions_reject_executable_or_invalid_rules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProcedureDynamicField::factory()->create([
            'visibility_conditions' => [[
                'field' => 'entry_year',
                'operator' => 'eval',
                'value' => 'phpinfo()',
            ]],
        ]);
    }

    public function test_existing_procedure_records_remain_compatible_with_new_defaults(): void
    {
        $procedureType = ProcedureType::factory()->create([
            'procedure_category_id' => null,
            'presentation_modality_id' => null,
        ]);
        $requirement = ProcedureRequirement::factory()->for($procedureType)->create([
            'procedure_variant_id' => null,
        ]);

        $this->assertNull($procedureType->category);
        $this->assertNull($procedureType->presentationModality);
        $this->assertNull($requirement->variant);
        $this->assertSame(RequirementType::DIGITAL_FILE, $requirement->type);
    }
}
