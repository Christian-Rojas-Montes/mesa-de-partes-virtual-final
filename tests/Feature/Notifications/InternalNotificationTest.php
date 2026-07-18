<?php

namespace Tests\Feature\Notifications;

use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\InternalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private InternalNotificationService $notifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = app(InternalNotificationService::class);

        foreach ([
            ['REGISTRADO', 'Registrado'],
            ['DERIVADO', 'Derivado'],
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

    public function test_required_events_reach_the_applicant_without_duplicates(): void
    {
        $applicant = $this->userWithRole('Solicitante');
        $request = $this->procedureRequest($applicant, 'REGISTRADO');
        $events = [
            InternalNotificationService::REGISTERED,
            InternalNotificationService::OBSERVED,
            InternalNotificationService::REJECTED,
            InternalNotificationService::DERIVED,
            InternalNotificationService::RECEIVED,
            InternalNotificationService::ATTENTION_STARTED,
            InternalNotificationService::RESPONSE_REGISTERED,
            InternalNotificationService::FINALIZED,
        ];

        foreach ($events as $event) {
            $this->notifications->dispatch($request, $event, 'Mensaje ficticio '.$event, 'occurrence');
            $this->notifications->dispatch($request, $event, 'Mensaje ficticio '.$event, 'occurrence');
        }

        $this->assertSame(count($events), $applicant->notifications()->count());
        $this->assertEqualsCanonicalizing($events, $applicant->notifications->pluck('data.event')->all());
    }

    public function test_correction_and_derivation_use_the_correct_internal_recipients(): void
    {
        $applicant = $this->userWithRole('Solicitante');
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $otherFrontDesk = $this->userWithRole('Mesa de Partes');
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $otherManager = $this->userWithRole('Responsable de área', $otherArea);
        $request = $this->procedureRequest($applicant, 'DERIVADO');

        $this->notifications->dispatch(
            $request,
            InternalNotificationService::CORRECTION_SUBMITTED,
            'Se presentó una subsanación ficticia.',
            'correction-1',
        );
        $this->assertSame(1, $frontDesk->notifications()->count());
        $this->assertSame(1, $otherFrontDesk->notifications()->count());
        $this->assertSame(1, $applicant->notifications()->count());

        $this->notifications->dispatch(
            $request,
            InternalNotificationService::RESPONSE_REGISTERED,
            'La respuesta ficticia está disponible.',
            'response-1',
        );
        $this->assertSame(2, $frontDesk->notifications()->count());
        $this->assertSame(2, $otherFrontDesk->notifications()->count());
        $this->assertSame(2, $applicant->notifications()->count());

        $this->notifications->dispatch(
            $request,
            InternalNotificationService::DERIVED,
            'El expediente fue derivado.',
            'derivation-1',
            $area->id,
        );
        $this->assertSame(3, $applicant->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $otherManager->notifications()->count());
    }

    public function test_bell_count_and_paginated_list_show_only_the_recipient_notifications(): void
    {
        $applicant = $this->userWithRole('Solicitante');
        $other = $this->userWithRole('Solicitante');
        $request = $this->procedureRequest($applicant, 'REGISTRADO');
        $otherRequest = $this->procedureRequest($other, 'REGISTRADO');

        foreach (range(1, 16) as $number) {
            $this->notifications->dispatch(
                $request,
                InternalNotificationService::REGISTERED,
                'Aviso ficticio '.$number,
                'notice-'.$number,
            );
        }
        $this->notifications->dispatch($otherRequest, InternalNotificationService::REGISTERED, 'Aviso ajeno.', 'other');

        $this->actingAs($applicant)->get(route('dashboard.applicant'))
            ->assertOk()->assertSee('Notificaciones: 16 no leídas');
        $response = $this->get(route('notifications.index'))
            ->assertOk()->assertSee('Aviso ficticio')->assertDontSee('Aviso ajeno.');

        $this->assertCount(15, $response->viewData('notifications')->items());
        $this->assertTrue($response->viewData('notifications')->hasMorePages());
    }

    public function test_recipient_can_mark_one_or_all_as_read_but_cannot_mutate_another_inbox(): void
    {
        $applicant = $this->userWithRole('Solicitante');
        $other = $this->userWithRole('Solicitante');
        $request = $this->procedureRequest($applicant, 'REGISTRADO');
        $otherRequest = $this->procedureRequest($other, 'REGISTRADO');
        $this->notifications->dispatch($request, InternalNotificationService::REGISTERED, 'Primera.', 'first');
        $this->notifications->dispatch($request, InternalNotificationService::OBSERVED, 'Segunda.', 'second');
        $this->notifications->dispatch($otherRequest, InternalNotificationService::REGISTERED, 'Ajena.', 'other');
        $first = $applicant->notifications()->latest()->firstOrFail();
        $foreign = $other->notifications()->firstOrFail();

        $this->actingAs($applicant)->patch(route('notifications.read', $first->id))->assertSessionHas('status');
        $this->assertNotNull($first->fresh()->read_at);
        $this->patch(route('notifications.read', $foreign->id))->assertNotFound();
        $this->patch(route('notifications.read-all'))->assertSessionHas('status');

        $this->assertSame(0, $applicant->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notification_link_marks_read_and_redirects_only_to_an_authorized_expedient(): void
    {
        $applicant = $this->userWithRole('Solicitante');
        $other = $this->userWithRole('Solicitante');
        $request = $this->procedureRequest($applicant, 'FINALIZADO');
        $this->notifications->dispatch($request, InternalNotificationService::FINALIZED, 'Expediente finalizado.', 'final');
        $notification = $applicant->notifications()->firstOrFail();

        $this->actingAs($applicant)->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('applicant.procedure-requests.show', $request));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($other)->post(route('notifications.open', $notification->id))->assertNotFound();
    }

    public function test_area_and_front_desk_links_resolve_to_their_authorized_views(): void
    {
        $applicant = $this->userWithRole('Solicitante');
        $frontDesk = $this->userWithRole('Mesa de Partes');
        $area = Area::factory()->create();
        $manager = $this->userWithRole('Responsable de área', $area);
        $request = $this->procedureRequest($applicant, 'DERIVADO');
        $request->derivations()->create([
            'to_area_id' => $area->id,
            'user_id' => $frontDesk->id,
            'reason' => null,
            'derived_at' => now(),
        ]);

        $this->notifications->dispatch($request, InternalNotificationService::DERIVED, 'Nueva asignación.', 'derive-link', $area->id);
        $managerNotification = $manager->notifications()->firstOrFail();
        $this->actingAs($manager)->post(route('notifications.open', $managerNotification->id))
            ->assertRedirect(route('area-manager.assignments.show', $request));

        $this->notifications->dispatch($request, InternalNotificationService::CORRECTION_SUBMITTED, 'Nueva subsanación.', 'correction-link');
        $frontDeskNotification = $frontDesk->notifications()->firstOrFail();
        $this->actingAs($frontDesk)->post(route('notifications.open', $frontDeskNotification->id))
            ->assertRedirect(route('front-desk.derivations.create', $request));
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

    private function procedureRequest(User $applicant, string $statusCode): ProcedureRequest
    {
        return ProcedureRequest::factory()->create([
            'user_id' => $applicant->id,
            'procedure_type_id' => ProcedureType::factory()->create()->id,
            'status_id' => Status::where('code', $statusCode)->value('id'),
        ]);
    }
}
