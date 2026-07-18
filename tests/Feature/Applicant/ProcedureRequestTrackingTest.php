<?php

namespace Tests\Feature\Applicant;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDerivation;
use App\Models\RequestDocument;
use App\Models\RequestHistory;
use App\Models\RequestObservation;
use App\Models\RequestResponse;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ProcedureRequestRegisteredNotification;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcedureRequestTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateDocumentStorage::DISK);
    }

    public function test_applicant_filters_only_own_requests_by_code_status_type_and_dates(): void
    {
        $applicant = $this->applicant();
        $otherApplicant = $this->applicant('otro.filtro@example.test');
        $registered = $this->makeStatus('REGISTRADO', 'Registrado', 1);
        $observed = $this->makeStatus('OBSERVADO', 'Observado', 2);
        $generalType = ProcedureType::factory()->create(['name' => 'Solicitud ficticia general']);
        $otherType = ProcedureType::factory()->create(['name' => 'Constancia ficticia']);

        $target = $this->request($applicant, $generalType, $observed, [
            'tracking_code' => 'MPV-2026-004321',
            'subject' => 'Resultado objetivo ficticio',
            'submitted_at' => '2026-05-15 10:00:00',
        ]);
        $this->request($applicant, $generalType, $registered, [
            'tracking_code' => 'MPV-2026-000111',
            'subject' => 'Estado diferente',
            'submitted_at' => '2026-05-15 11:00:00',
        ]);
        $this->request($applicant, $otherType, $observed, [
            'tracking_code' => 'MPV-2026-000222',
            'subject' => 'Trámite diferente',
            'submitted_at' => '2026-05-15 12:00:00',
        ]);
        $this->request($applicant, $generalType, $observed, [
            'tracking_code' => 'MPV-2026-000333',
            'subject' => 'Fecha diferente',
            'submitted_at' => '2026-04-01 09:00:00',
        ]);
        $foreign = $this->request($otherApplicant, $generalType, $observed, [
            'tracking_code' => 'MPV-2026-004321-X',
            'subject' => 'Solicitud ajena sensible',
            'submitted_at' => '2026-05-15 10:00:00',
        ]);

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.index', [
            'codigo' => '004321',
            'estado' => $observed->id,
            'tramite' => $generalType->id,
            'desde' => '2026-05-01',
            'hasta' => '2026-05-31',
        ]))->assertOk()
            ->assertSee($target->subject)
            ->assertDontSee('Estado diferente')
            ->assertDontSee('Trámite diferente')
            ->assertDontSee('Fecha diferente')
            ->assertDontSee($foreign->subject);
    }

    public function test_tracking_list_is_paginated_and_rejects_an_invalid_date_range(): void
    {
        $applicant = $this->applicant();
        $status = $this->makeStatus('REGISTRADO', 'Registrado', 1);
        $type = ProcedureType::factory()->create();

        ProcedureRequest::factory()->count(11)->create([
            'user_id' => $applicant->id,
            'procedure_type_id' => $type->id,
            'status_id' => $status->id,
        ]);

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.index'))
            ->assertOk()->assertSee('pagination');

        $this->get(route('applicant.procedure-requests.index', [
            'desde' => '2026-06-30',
            'hasta' => '2026-06-01',
        ]))->assertSessionHasErrors('hasta');
    }

    public function test_detail_shows_timeline_observations_derivations_response_and_related_notifications(): void
    {
        $applicant = $this->applicant();
        $registered = $this->makeStatus('REGISTRADO', 'Registrado', 1);
        $observed = $this->makeStatus('OBSERVADO', 'Observado', 2);
        $derived = $this->makeStatus('DERIVADO', 'Derivado', 3);
        $type = ProcedureType::factory()->create();
        $procedureRequest = $this->request($applicant, $type, $derived);
        $internal = $this->internalUser('NombreInternoNoVisible');
        $destination = Area::factory()->create(['name' => 'Área ficticia de atención']);

        RequestHistory::query()->create([
            'procedure_request_id' => $procedureRequest->id,
            'status_id' => $registered->id,
            'user_id' => $applicant->id,
            'action' => 'registered',
            'description' => 'Solicitud registrada correctamente.',
            'created_at' => '2026-05-01 09:00:00',
        ]);
        RequestHistory::query()->create([
            'procedure_request_id' => $procedureRequest->id,
            'status_id' => $observed->id,
            'user_id' => $internal->id,
            'action' => 'observed',
            'description' => 'Se solicitó una corrección ficticia.',
            'created_at' => '2026-05-02 10:00:00',
        ]);
        RequestHistory::query()->create([
            'procedure_request_id' => $procedureRequest->id,
            'status_id' => $derived->id,
            'user_id' => $internal->id,
            'action' => 'derived',
            'description' => 'La solicitud fue enviada al área correspondiente.',
            'created_at' => '2026-05-03 11:00:00',
        ]);
        RequestObservation::factory()->for($procedureRequest)->create([
            'user_id' => $internal->id,
            'description' => 'Falta un documento ficticio legible.',
            'correction_instructions' => 'Adjunta una copia ficticia en formato PDF.',
        ]);
        RequestDerivation::factory()->for($procedureRequest)->create([
            'user_id' => $internal->id,
            'to_area_id' => $destination->id,
            'reason' => 'Corresponde al área encargada del trámite.',
        ]);
        $response = RequestResponse::factory()->for($procedureRequest)->create([
            'user_id' => $internal->id,
            'summary' => 'La solicitud ficticia fue atendida favorablemente.',
        ]);
        $applicant->notify(new ProcedureRequestRegisteredNotification($procedureRequest));
        AuditLog::query()->create([
            'user_id' => $internal->id,
            'action' => 'dato_tecnico_no_visible',
            'auditable_type' => ProcedureRequest::class,
            'auditable_id' => $procedureRequest->id,
            'details' => ['secret' => 'AUDITORIA_INTERNA_NO_VISIBLE'],
        ]);

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.show', $procedureRequest))
            ->assertOk()
            ->assertSee('Estado actual: Derivado')
            ->assertSeeInOrder(['Registrado', 'Observado', 'Derivado'])
            ->assertSee('Falta un documento ficticio legible.')
            ->assertSee('Adjunta una copia ficticia en formato PDF.')
            ->assertSee($destination->name)
            ->assertSee('Corresponde al área encargada del trámite.')
            ->assertSee($response->summary)
            ->assertSee('Tu solicitud fue registrada correctamente.')
            ->assertDontSee($internal->first_name)
            ->assertDontSee('AUDITORIA_INTERNA_NO_VISIBLE')
            ->assertDontSee($response->path)
            ->assertDontSee($response->checksum_sha256);
    }

    public function test_owner_can_download_documents_and_response_but_another_applicant_cannot(): void
    {
        $owner = $this->applicant();
        $otherApplicant = $this->applicant('otro.descarga@example.test');
        $status = $this->makeStatus('ATENDIDO', 'Atendido', 1);
        $type = ProcedureType::factory()->create();
        $procedureRequest = $this->request($owner, $type, $status);
        $internal = $this->internalUser();

        Storage::disk(PrivateDocumentStorage::DISK)->put('requests/own/document.pdf', '%PDF contenido ficticio');
        Storage::disk(PrivateDocumentStorage::DISK)->put('responses/own/response.pdf', '%PDF respuesta ficticia');
        $document = RequestDocument::query()->create([
            'procedure_request_id' => $procedureRequest->id,
            'procedure_requirement_id' => null,
            'disk' => PrivateDocumentStorage::DISK,
            'path' => 'requests/own/document.pdf',
            'stored_name' => 'document.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 22,
            'checksum_sha256' => str_repeat('a', 64),
        ]);
        RequestResponse::factory()->for($procedureRequest)->create([
            'user_id' => $internal->id,
            'disk' => PrivateDocumentStorage::DISK,
            'path' => 'responses/own/response.pdf',
            'stored_name' => 'response.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('applicant.procedure-requests.documents.download', [$procedureRequest, $document]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('applicant.procedure-requests.response.download', $procedureRequest))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($otherApplicant)
            ->get(route('applicant.procedure-requests.documents.download', [$procedureRequest, $document]))
            ->assertForbidden();
        $this->get(route('applicant.procedure-requests.response.download', $procedureRequest))->assertForbidden();
    }

    public function test_changing_request_or_document_id_does_not_expose_foreign_information(): void
    {
        $owner = $this->applicant();
        $attacker = $this->applicant('solicitante.ajeno@example.test');
        $status = $this->makeStatus('REGISTRADO', 'Registrado', 1);
        $type = ProcedureType::factory()->create();
        $ownerRequest = $this->request($owner, $type, $status, ['subject' => 'Contenido privado ficticio']);
        $attackerRequest = $this->request($attacker, $type, $status);
        Storage::disk(PrivateDocumentStorage::DISK)->put('requests/owner.pdf', '%PDF privado');
        $ownerDocument = RequestDocument::query()->create([
            'procedure_request_id' => $ownerRequest->id,
            'disk' => 'private',
            'path' => 'requests/owner.pdf',
            'stored_name' => 'owner.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'checksum_sha256' => str_repeat('b', 64),
        ]);

        $this->actingAs($attacker)->get(route('applicant.procedure-requests.show', $ownerRequest))
            ->assertForbidden()->assertDontSee('Contenido privado ficticio');
        $this->get(route('applicant.procedure-requests.documents.download', [$ownerRequest, $ownerDocument]))
            ->assertForbidden();
        $this->get(route('applicant.procedure-requests.documents.download', [$attackerRequest, $ownerDocument]))
            ->assertNotFound();
    }

    public function test_sent_request_has_no_update_or_delete_endpoint(): void
    {
        $applicant = $this->applicant();
        $procedureRequest = $this->request(
            $applicant,
            ProcedureType::factory()->create(),
            $this->makeStatus('REGISTRADO', 'Registrado', 1),
        );

        $this->actingAs($applicant)
            ->put('/panel/solicitante/solicitudes/'.$procedureRequest->id, ['subject' => 'Cambio no permitido'])
            ->assertMethodNotAllowed();
        $this->delete('/panel/solicitante/solicitudes/'.$procedureRequest->id)
            ->assertMethodNotAllowed();
        $this->assertSame('Asunto ficticio', $procedureRequest->fresh()->subject);
    }

    private function applicant(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'Solicitante'], [
            'description' => 'Rol ficticio solicitante.',
            'active' => true,
        ]);

        return User::factory()->for($role)->create([
            'area_id' => null,
            'email' => $email ?? fake()->unique()->userName().'@example.test',
            'active' => true,
        ]);
    }

    private function internalUser(string $firstName = 'PersonalFicticio'): User
    {
        $role = Role::firstOrCreate(['name' => 'Mesa de Partes'], [
            'description' => 'Rol interno ficticio.',
            'active' => true,
        ]);

        return User::factory()->for($role)->create(['first_name' => $firstName, 'active' => true]);
    }

    private function makeStatus(string $code, string $name, int $order): Status
    {
        return Status::firstOrCreate(['code' => $code], [
            'name' => $name,
            'description' => "Estado ficticio {$name}.",
            'sort_order' => $order,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function request(User $user, ProcedureType $type, Status $status, array $attributes = []): ProcedureRequest
    {
        return ProcedureRequest::factory()->create([
            'user_id' => $user->id,
            'procedure_type_id' => $type->id,
            'status_id' => $status->id,
            'subject' => 'Asunto ficticio',
            ...$attributes,
        ]);
    }
}
