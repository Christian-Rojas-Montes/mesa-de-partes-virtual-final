<?php

namespace Tests\Feature\History;

use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestAppointment;
use App\Models\RequestAttentionAction;
use App\Models\RequestDerivation;
use App\Models\RequestHistory;
use App\Models\RequestObservation;
use App\Models\RequestPhysicalReception;
use App\Models\RequestPickup;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_is_private_chronological_and_projects_public_and_internal_details_safely(): void
    {
        $applicant = $this->user('Solicitante');
        $other = $this->user('Solicitante');
        $staff = $this->user('Mesa de Partes', 'PersonalHistorial');
        $registered = Status::factory()->create(['code' => 'REGISTRADO', 'name' => 'Registrado', 'sort_order' => 1]);
        $derived = Status::factory()->create(['code' => 'DERIVADO', 'name' => 'Derivado', 'sort_order' => 2]);
        $request = ProcedureRequest::factory()->create([
            'user_id' => $applicant->id,
            'procedure_type_id' => ProcedureType::factory(),
            'status_id' => $derived->id,
        ]);
        $area = Area::factory()->create(['name' => 'Área Académica Ficticia']);

        RequestHistory::query()->create(['procedure_request_id' => $request->id, 'status_id' => $registered->id, 'user_id' => $applicant->id, 'action' => 'registered', 'description' => 'Inicio del expediente.']);
        RequestObservation::factory()->for($request)->create(['user_id' => $staff->id, 'description' => 'Debe subsanar el documento ficticio.']);
        RequestDerivation::factory()->for($request)->create(['user_id' => $staff->id, 'to_area_id' => $area->id, 'reason' => 'Evaluación académica ficticia.']);
        RequestPhysicalReception::query()->create([
            'procedure_request_id' => $request->id, 'received_at' => now(), 'received_by' => $staff->id,
            'folio_count' => 2, 'document_count' => 1, 'presented_documents' => [['name' => 'Constancia ficticia', 'presentation' => 'original', 'quantity' => 1]],
            'observations' => 'NOTA_INTERNA_RECEPCION', 'receiving_area_id' => $area->id, 'verification_result' => 'completo',
        ]);
        RequestAppointment::query()->create([
            'procedure_request_id' => $request->id, 'appointment_date' => now()->addDay(), 'starts_at' => '09:00', 'ends_at' => '09:30',
            'office' => 'Oficina 1', 'area_id' => $area->id, 'reason' => 'Revisión', 'status' => 'programada', 'created_by' => $staff->id,
        ]);
        RequestPickup::query()->create([
            'procedure_request_id' => $request->id, 'available_at' => now(), 'office' => 'Mesa de Partes', 'pickup_requirement' => 'Presentar identificación',
            'marked_ready_by' => $staff->id, 'delivered_by' => $staff->id, 'received_by_name' => 'Ciudadano Ficticio',
            'identity_document_verified' => true, 'delivered_at' => now(), 'observation' => 'NOTA_INTERNA_ENTREGA', 'status' => 'entregado',
        ]);
        RequestAttentionAction::query()->create(['procedure_request_id' => $request->id, 'user_id' => $staff->id, 'description' => 'NOTA_INTERNA_ATENCION']);

        $this->actingAs($other)->get(route('applicant.procedure-requests.show', $request))->assertForbidden();

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.show', $request))
            ->assertOk()
            ->assertSeeInOrder(['Solicitud registrada', 'Observación registrada', 'Expediente derivado'])
            ->assertSee('Documentación física recibida')->assertSee('Cita programada')->assertSee('Documento entregado')
            ->assertDontSee('NOTA_INTERNA_RECEPCION')->assertDontSee('NOTA_INTERNA_ENTREGA')
            ->assertDontSee('NOTA_INTERNA_ATENCION')->assertDontSee('PersonalHistorial');

        $this->actingAs($staff)->get(route('history.staff', $request))
            ->assertOk()->assertSee('Constancia ficticia')->assertSee('NOTA_INTERNA_RECEPCION')
            ->assertSee('NOTA_INTERNA_ENTREGA')->assertSee('NOTA_INTERNA_ATENCION')->assertSee('PersonalHistorial')
            ->assertSee('Estado anterior')->assertSee('Estado nuevo');

        $this->get(route('history.staff', [$request, 'tipo' => 'appointment']))
            ->assertOk()->assertSee('Cita programada')->assertDontSee('NOTA_INTERNA_ATENCION');
    }

    private function user(string $roleName, ?string $firstName = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['description' => 'Rol ficticio', 'active' => true]);

        return User::factory()->for($role)->create(['first_name' => $firstName ?? fake()->firstName(), 'active' => true]);
    }
}
