<?php

namespace Tests\Feature\Derivation;

use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDerivation;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestDerivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['EN_REVISION', 'En revisión'], ['DERIVADO', 'Derivado']] as $index => [$code, $name]) {
            Status::query()->create([
                'code' => $code,
                'name' => $name,
                'description' => 'Estado ficticio '.$name.'.',
                'sort_order' => $index + 1,
                'active' => true,
            ]);
        }
    }

    public function test_only_a_validated_request_can_be_derived_to_an_active_area(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $activeArea = Area::factory()->create(['name' => 'Área ficticia activa', 'active' => true]);
        $inactiveArea = Area::factory()->create(['name' => 'Área ficticia inactiva', 'active' => false]);
        $request = $this->procedureRequest($applicant, 'EN_REVISION');

        $this->actingAs($frontDesk)->post(route('front-desk.derivations.store', $request), [
            'area_id' => $activeArea->id,
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('request_derivations', 0);

        $request->update(['validated_by' => $frontDesk->id, 'validated_at' => now()]);
        $this->post(route('front-desk.derivations.store', $request), [
            'area_id' => $inactiveArea->id,
        ])->assertSessionHasErrors('area_id');
        $this->assertDatabaseCount('request_derivations', 0);

        $this->post(route('front-desk.derivations.store', $request), [
            'area_id' => $activeArea->id,
        ])->assertRedirect(route('front-desk.derivations.create', $request));

        $derivation = RequestDerivation::firstOrFail();
        $this->assertNull($derivation->reason);
        $this->assertSame($frontDesk->id, $derivation->user_id);
        $this->assertNotNull($derivation->derived_at);
        $this->assertSame('DERIVADO', $request->fresh()->status->code);
    }

    public function test_derivation_records_history_audit_and_notifications_for_applicant_and_destination_area(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $area = Area::factory()->create(['active' => true]);
        $destinationManager = $this->userWithRole('Responsable de área', $area);
        $otherManager = $this->userWithRole('Responsable de área', Area::factory()->create());
        $request = $this->validatedRequest($applicant, $frontDesk);

        $this->actingAs($frontDesk)->post(route('front-desk.derivations.store', $request), [
            'area_id' => $area->id,
            'reason' => 'Competencia del área ficticia.',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('request_histories', [
            'procedure_request_id' => $request->id,
            'user_id' => $frontDesk->id,
            'action' => 'derived',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $request->id,
            'user_id' => $frontDesk->id,
            'action' => 'derived',
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $destinationManager->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $otherManager->id]);
    }

    public function test_correction_creates_a_new_traceable_derivation_and_rejects_same_destination(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $firstArea = Area::factory()->create(['active' => true]);
        $secondArea = Area::factory()->create(['active' => true]);
        $request = $this->validatedRequest($applicant, $frontDesk);

        $this->actingAs($frontDesk)->post(route('front-desk.derivations.store', $request), ['area_id' => $firstArea->id]);
        $first = RequestDerivation::firstOrFail();

        $this->post(route('front-desk.derivations.store', $request), ['area_id' => $firstArea->id])
            ->assertSessionHasErrors('area_id');
        $this->assertDatabaseCount('request_derivations', 1);

        $this->post(route('front-desk.derivations.store', $request), [
            'area_id' => $secondArea->id,
            'reason' => 'Corrección de destino ficticia.',
        ])->assertSessionHas('status');

        $this->assertDatabaseCount('request_derivations', 2);
        $this->assertDatabaseHas('request_derivations', ['id' => $first->id, 'to_area_id' => $firstArea->id]);
        $this->assertDatabaseHas('request_derivations', [
            'procedure_request_id' => $request->id,
            'from_area_id' => $firstArea->id,
            'to_area_id' => $secondArea->id,
            'reason' => 'Corrección de destino ficticia.',
        ]);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'rederived']);
    }

    public function test_permissions_are_enforced_for_derivation_and_area_assignments(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $assignedManager = $this->userWithRole('Responsable de área', $area);
        $otherManager = $this->userWithRole('Responsable de área', $otherArea);
        $request = $this->derivedRequest($applicant, $frontDesk, $area);

        $this->actingAs($applicant)->get(route('front-desk.derivations.index'))->assertForbidden();
        $this->post(route('front-desk.derivations.store', $request), ['area_id' => $otherArea->id])->assertForbidden();

        $this->actingAs($assignedManager)->get(route('area-manager.assignments.show', $request))->assertOk();
        $this->actingAs($otherManager)->get(route('area-manager.assignments.show', $request))->assertForbidden();
        $this->actingAs($frontDesk)->get(route('area-manager.assignments.index'))->assertForbidden();
    }

    public function test_area_inbox_only_contains_current_assignments_and_supports_filters(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $type = ProcedureType::factory()->create(['name' => 'Trámite objetivo ficticio']);
        $target = $this->derivedRequest($applicant, $frontDesk, $area, $type, 'MPV-2026-009991');
        $received = $this->derivedRequest($applicant, $frontDesk, $area, $type, 'MPV-2026-009992');
        $received->latestDerivation->update(['received_at' => now()]);
        $outside = $this->derivedRequest($applicant, $frontDesk, $otherArea, $type, 'MPV-2026-009993');

        $this->actingAs($manager)->get(route('area-manager.assignments.index', [
            'codigo' => '009991',
            'tramite' => $type->id,
            'recepcion' => 'pendiente',
        ]))->assertOk()->assertSee($target->tracking_code)
            ->assertDontSee($received->tracking_code)
            ->assertDontSee($outside->tracking_code);
    }

    public function test_current_area_can_register_receipt_only_once_with_traceability(): void
    {
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $request = $this->derivedRequest($applicant, $frontDesk, $area);
        $derivation = $request->latestDerivation;

        $this->actingAs($manager)->patch(route('area-manager.assignments.receive', [$request, $derivation]))
            ->assertSessionHas('status');
        $this->assertNotNull($derivation->fresh()->received_at);
        $this->assertSame('DERIVADO', $request->fresh()->status->code);
        $this->assertDatabaseHas('request_histories', [
            'procedure_request_id' => $request->id,
            'user_id' => $manager->id,
            'action' => 'derivation_received',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'derivation_received']);

        $historyCount = $request->histories()->count();
        $this->patch(route('area-manager.assignments.receive', [$request, $derivation]))
            ->assertSessionHasErrors('action');
        $this->assertSame($historyCount, $request->histories()->count());
    }

    private function userWithRole(string $roleName, ?Area $area = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], [
            'description' => 'Rol ficticio '.$roleName.'.',
            'active' => true,
        ]);

        return User::factory()->for($role)->create([
            'area_id' => $area?->id,
            'email' => fake()->unique()->userName().'@example.test',
            'active' => true,
        ]);
    }

    private function procedureRequest(User $applicant, string $statusCode, ?ProcedureType $type = null, ?string $code = null): ProcedureRequest
    {
        return ProcedureRequest::factory()->create([
            'user_id' => $applicant->id,
            'procedure_type_id' => ($type ?? ProcedureType::factory()->create())->id,
            'status_id' => Status::where('code', $statusCode)->value('id'),
            'tracking_code' => $code ?? fake()->unique()->numerify('MPV-2026-######'),
        ]);
    }

    private function validatedRequest(User $applicant, User $frontDesk): ProcedureRequest
    {
        $request = $this->procedureRequest($applicant, 'EN_REVISION');
        $request->update(['validated_by' => $frontDesk->id, 'validated_at' => now()]);

        return $request;
    }

    private function derivedRequest(
        User $applicant,
        User $frontDesk,
        Area $area,
        ?ProcedureType $type = null,
        ?string $code = null,
    ): ProcedureRequest {
        $request = $this->procedureRequest($applicant, 'DERIVADO', $type, $code);
        $request->derivations()->create([
            'to_area_id' => $area->id,
            'user_id' => $frontDesk->id,
            'reason' => null,
            'derived_at' => now(),
        ]);

        return $request->load('latestDerivation');
    }
}
