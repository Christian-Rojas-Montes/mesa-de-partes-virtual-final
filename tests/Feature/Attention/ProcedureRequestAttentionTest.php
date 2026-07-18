<?php

namespace Tests\Feature\Attention;

use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestResponse;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcedureRequestAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateDocumentStorage::DISK);

        foreach ([
            ['DERIVADO', 'Derivado'],
            ['EN_ATENCION', 'En atención'],
            ['ATENDIDO', 'Atendido'],
            ['FINALIZADO', 'Finalizado'],
        ] as $index => [$code, $name]) {
            Status::query()->create([
                'code' => $code,
                'name' => $name,
                'description' => 'Estado ficticio '.$name.'.',
                'sort_order' => $index + 1,
                'active' => true,
            ]);
        }
    }

    public function test_assigned_area_starts_attention_after_receipt_with_traceability(): void
    {
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $applicant = $this->userWithRole('Solicitante');
        $request = $this->assignedRequest($applicant, $manager, $area, 'DERIVADO', true);

        $this->actingAs($manager)->patch(route('area-manager.assignments.attention.start', $request))
            ->assertSessionHas('status');

        $this->assertSame('EN_ATENCION', $request->fresh()->status->code);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'attention_started']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'attention_started']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);

        $this->patch(route('area-manager.assignments.attention.start', $request))->assertSessionHasErrors('action');
    }

    public function test_attention_cannot_start_before_receipt_or_from_another_area(): void
    {
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $otherManager = $this->userWithRole('Responsable de área', $otherArea);
        $applicant = $this->userWithRole('Solicitante');
        $request = $this->assignedRequest($applicant, $manager, $area, 'DERIVADO', false);

        $this->actingAs($manager)->patch(route('area-manager.assignments.attention.start', $request))
            ->assertSessionHasErrors('action');
        $this->actingAs($otherManager)->patch(route('area-manager.assignments.attention.start', $request))
            ->assertForbidden();
        $this->assertSame('DERIVADO', $request->fresh()->status->code);
    }

    public function test_assigned_manager_registers_append_only_attention_actions(): void
    {
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $applicant = $this->userWithRole('Solicitante');
        $request = $this->assignedRequest($applicant, $manager, $area, 'EN_ATENCION', true);

        $this->actingAs($manager)->post(route('area-manager.assignments.attention-actions.store', $request), [
            'description' => 'Se verificó el expediente ficticio y sus anexos.',
        ])->assertSessionHas('status');
        $this->post(route('area-manager.assignments.attention-actions.store', $request), [
            'description' => 'Se elaboró el proyecto de respuesta ficticia.',
        ])->assertSessionHas('status');

        $this->assertDatabaseCount('request_attention_actions', 2);
        $this->assertDatabaseHas('request_attention_actions', [
            'procedure_request_id' => $request->id,
            'user_id' => $manager->id,
            'description' => 'Se verificó el expediente ficticio y sus anexos.',
        ]);
        $this->assertSame(2, $request->histories()->where('action', 'attention_action')->count());
    }

    public function test_response_is_private_identifies_author_and_changes_status_to_attended(): void
    {
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $applicant = $this->userWithRole('Solicitante');
        $request = $this->assignedRequest($applicant, $manager, $area, 'EN_ATENCION', true);

        $this->actingAs($manager)->post(route('area-manager.assignments.response.store', $request), [
            'summary' => 'Respuesta final ficticia favorable.',
            'document' => $this->validPdf(),
        ])->assertSessionHas('status');

        $response = RequestResponse::firstOrFail();
        $this->assertSame($manager->id, $response->user_id);
        $this->assertNotNull($response->responded_at);
        $this->assertStringStartsWith('responses/'.$request->id.'/', $response->path);
        Storage::disk('private')->assertExists($response->path);
        $this->assertSame('ATENDIDO', $request->fresh()->status->code);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'response_registered']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'response_registered']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);

        $this->post(route('area-manager.assignments.response.store', $request), [
            'summary' => 'Segunda respuesta no permitida.',
            'document' => $this->validPdf(),
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('request_responses', 1);
    }

    public function test_response_rejects_invalid_content_and_files_over_five_megabytes(): void
    {
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $request = $this->assignedRequest($this->userWithRole('Solicitante'), $manager, $area, 'EN_ATENCION', true);

        $this->actingAs($manager)->post(route('area-manager.assignments.response.store', $request), [
            'summary' => 'Respuesta ficticia.',
            'document' => UploadedFile::fake()->createWithContent('falso.pdf', 'contenido que no es PDF'),
        ])->assertSessionHasErrors('document');

        $this->post(route('area-manager.assignments.response.store', $request), [
            'summary' => 'Respuesta ficticia.',
            'document' => UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('request_responses', 0);
    }

    public function test_applicant_can_view_and_download_only_own_response(): void
    {
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $applicant = $this->userWithRole('Solicitante');
        $otherApplicant = $this->userWithRole('Solicitante');
        $request = $this->assignedRequest($applicant, $manager, $area, 'FINALIZADO', true);
        $response = $this->response($request, $manager);

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.show', $request))
            ->assertOk()->assertSee('Finalizado')->assertSee($response->summary)->assertSee($area->name);
        $this->get(route('applicant.procedure-requests.response.download', $request))->assertOk();

        $this->actingAs($otherApplicant)->get(route('applicant.procedure-requests.response.download', $request))
            ->assertForbidden();
    }

    public function test_front_desk_finalizes_only_attended_requests_with_a_response(): void
    {
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $applicant = $this->userWithRole('Solicitante');
        $withoutResponse = $this->assignedRequest($applicant, $manager, $area, 'ATENDIDO', true);

        $this->actingAs($frontDesk)->patch(route('front-desk.closures.finalize', $withoutResponse))
            ->assertSessionHasErrors('action');
        $this->assertSame('ATENDIDO', $withoutResponse->fresh()->status->code);

        $request = $this->assignedRequest($applicant, $manager, $area, 'ATENDIDO', true);
        $response = $this->response($request, $manager);
        $this->get(route('front-desk.closures.show', $request))->assertOk()->assertSee($response->summary);
        $this->get(route('front-desk.closures.response.download', $request))->assertOk();
        $this->patch(route('front-desk.closures.finalize', $request))->assertSessionHas('status');

        $this->assertSame('FINALIZADO', $request->fresh()->status->code);
        $this->assertDatabaseHas('request_histories', ['procedure_request_id' => $request->id, 'action' => 'request_finalized']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'action' => 'request_finalized']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);

        $this->patch(route('front-desk.closures.finalize', $request))->assertSessionHasErrors('action');
        $this->actingAs($manager)->patch(route('front-desk.closures.finalize', $withoutResponse))->assertForbidden();
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

    private function assignedRequest(
        User $applicant,
        User $manager,
        Area $area,
        string $statusCode,
        bool $received,
    ): ProcedureRequest {
        $request = ProcedureRequest::factory()->create([
            'user_id' => $applicant->id,
            'procedure_type_id' => ProcedureType::factory()->create()->id,
            'status_id' => Status::where('code', $statusCode)->value('id'),
        ]);
        $request->derivations()->create([
            'to_area_id' => $area->id,
            'user_id' => $manager->id,
            'reason' => 'Asignación ficticia para atención.',
            'derived_at' => now(),
            'received_at' => $received ? now() : null,
        ]);

        return $request;
    }

    private function response(ProcedureRequest $request, User $manager): RequestResponse
    {
        Storage::disk('private')->put('responses/'.$request->id.'/respuesta.pdf', '%PDF respuesta ficticia');

        return $request->response()->create([
            'user_id' => $manager->id,
            'summary' => 'Respuesta final ficticia para descarga.',
            'responded_at' => now(),
            'disk' => 'private',
            'path' => 'responses/'.$request->id.'/respuesta.pdf',
            'stored_name' => 'respuesta.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 24,
            'checksum_sha256' => str_repeat('a', 64),
        ]);
    }

    private function validPdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'respuesta-ficticia.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );
    }
}
