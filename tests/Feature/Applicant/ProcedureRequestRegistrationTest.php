<?php

namespace Tests\Feature\Applicant;

use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcedureRequestRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateDocumentStorage::DISK);
    }

    public function test_applicant_sees_only_active_procedure_types_and_requirements(): void
    {
        $applicant = $this->applicant();
        $activeType = ProcedureType::factory()->create(['name' => 'Trámite ficticio activo', 'active' => true]);
        $inactiveType = ProcedureType::factory()->create(['name' => 'Trámite ficticio inactivo', 'active' => false]);
        ProcedureRequirement::factory()->for($activeType)->create(['name' => 'Requisito ficticio activo', 'active' => true]);
        ProcedureRequirement::factory()->for($activeType)->create(['name' => 'Requisito ficticio inactivo', 'active' => false]);

        $this->actingAs($applicant)->get(route('applicant.procedure-requests.create'))
            ->assertOk()->assertSee($activeType->name)->assertDontSee($inactiveType->name);

        $this->get(route('applicant.procedure-requests.create', ['tipo' => $activeType->id]))
            ->assertOk()
            ->assertSee('Requisito ficticio activo')
            ->assertDontSee('Requisito ficticio inactivo');
    }

    public function test_applicant_registers_request_with_private_documents_history_notification_and_audit(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements(2);

        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
            'procedure_type_id' => $procedureType->id,
            'subject' => 'Solicitud ficticia para prueba',
            'description' => 'Descripción completamente ficticia del trámite presentado.',
            'documents' => [
                $requirements[0]->id => $this->validPdf(),
                $requirements[1]->id => $this->validPng('evidencia-ficticia.png'),
            ],
            'confirmation' => '1',
        ])->assertRedirect();

        $procedureRequest = ProcedureRequest::with('documents')->firstOrFail();
        $this->assertMatchesRegularExpression('/^MPV-'.now()->format('Y').'-\d{6}$/', $procedureRequest->tracking_code);
        $this->assertSame('REGISTRADO', $procedureRequest->status->code);
        $this->assertSame($applicant->id, $procedureRequest->user_id);
        $this->assertCount(2, $procedureRequest->documents);
        $this->assertDatabaseHas('request_histories', [
            'procedure_request_id' => $procedureRequest->id,
            'status_id' => $procedureRequest->status_id,
            'action' => 'registered',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $applicant->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $applicant->id,
            'action' => 'submitted',
            'auditable_type' => ProcedureRequest::class,
            'auditable_id' => $procedureRequest->id,
        ]);

        foreach ($procedureRequest->documents as $document) {
            Storage::disk(PrivateDocumentStorage::DISK)->assertExists($document->path);
            $this->assertSame(PrivateDocumentStorage::DISK, $document->disk);
            $this->assertSame(64, strlen($document->checksum_sha256));
            $this->assertStringNotContainsString('evidencia-ficticia', $document->stored_name);
        }
    }

    public function test_tracking_codes_are_sequential_and_unique_without_counting_requests(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements();

        foreach (['Primera solicitud ficticia', 'Segunda solicitud ficticia'] as $subject) {
            $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
                'procedure_type_id' => $procedureType->id,
                'subject' => $subject,
                'description' => 'Descripción ficticia válida para comprobar la secuencia.',
                'documents' => [$requirements[0]->id => $this->validPdf()],
                'confirmation' => '1',
            ])->assertRedirect();
        }

        $codes = ProcedureRequest::orderBy('id')->pluck('tracking_code')->all();
        $year = now()->format('Y');
        $this->assertSame(["MPV-{$year}-000001", "MPV-{$year}-000002"], $codes);
        $this->assertDatabaseHas('tracking_sequences', ['year' => $year, 'last_number' => 2]);
    }

    public function test_request_rejects_invalid_extension_and_unrecognized_content(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements();

        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
            'procedure_type_id' => $procedureType->id,
            'subject' => 'Solicitud con archivo inválido',
            'description' => 'Descripción ficticia para validar archivos rechazados.',
            'documents' => [
                $requirements[0]->id => UploadedFile::fake()->createWithContent('contenido-falso.pdf', 'Esto no es un PDF.'),
            ],
            'confirmation' => '1',
        ])->assertSessionHasErrors('documents');

        $this->assertDatabaseCount('procedure_requests', 0);
        $this->assertSame([], Storage::disk(PrivateDocumentStorage::DISK)->allFiles());
    }

    public function test_request_rejects_files_larger_than_five_megabytes(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements();

        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
            ...$this->baseData($procedureType),
            'documents' => [
                $requirements[0]->id => UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf'),
            ],
        ])->assertSessionHasErrors("documents.{$requirements[0]->id}");

        $this->assertDatabaseCount('procedure_requests', 0);
    }

    public function test_inactive_procedure_type_and_missing_confirmation_are_rejected(): void
    {
        $applicant = $this->applicant();
        $this->registeredStatus();
        $inactiveType = ProcedureType::factory()->create(['active' => false]);

        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
            'procedure_type_id' => $inactiveType->id,
            'subject' => 'Solicitud ficticia no confirmada',
            'description' => 'Descripción ficticia asociada a un trámite inactivo.',
            'documents' => ['general' => $this->validPdf()],
        ])->assertSessionHasErrors(['procedure_type_id', 'confirmation']);

        $this->assertDatabaseCount('procedure_requests', 0);
    }

    public function test_request_rejects_more_than_five_files(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements(6, false);
        $documents = [];

        foreach ($requirements as $requirement) {
            $documents[$requirement->id] = $this->validPng("archivo-{$requirement->id}.png");
        }

        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
            ...$this->baseData($procedureType),
            'documents' => $documents,
        ])->assertSessionHasErrors('documents');

        $this->assertDatabaseCount('procedure_requests', 0);
    }

    public function test_required_active_document_is_enforced_but_inactive_requirement_is_not(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements();
        ProcedureRequirement::factory()->for($procedureType)->create(['required' => true, 'active' => false]);

        $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
            ...$this->baseData($procedureType),
            'documents' => ['general' => $this->validPdf()],
        ])->assertSessionHasErrors("documents.{$requirements[0]->id}");

        $this->post(route('applicant.procedure-requests.store'), [
            ...$this->baseData($procedureType),
            'documents' => [$requirements[0]->id => $this->validPdf()],
        ])->assertRedirect();
        $this->assertDatabaseCount('procedure_requests', 1);
    }

    public function test_storage_failure_rolls_back_database_and_removes_stored_files(): void
    {
        $applicant = $this->applicant();
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements(2);
        Storage::disk(PrivateDocumentStorage::DISK)->put('requests/temp/first.pdf', 'contenido temporal');

        $failingStorage = new class extends PrivateDocumentStorage
        {
            private int $calls = 0;

            public function store(UploadedFile $file, ProcedureRequest $procedureRequest): array
            {
                $this->calls++;

                if ($this->calls === 2) {
                    throw new RuntimeException('Fallo simulado de almacenamiento.');
                }

                return [
                    'disk' => self::DISK,
                    'path' => 'requests/temp/first.pdf',
                    'stored_name' => 'first.pdf',
                    'extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 18,
                    'checksum_sha256' => str_repeat('a', 64),
                ];
            }
        };
        $this->app->instance(PrivateDocumentStorage::class, $failingStorage);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($applicant)->post(route('applicant.procedure-requests.store'), [
                ...$this->baseData($procedureType),
                'documents' => [
                    $requirements[0]->id => $this->validPdf(),
                    $requirements[1]->id => $this->validPng('segunda.png'),
                ],
            ]);
            $this->fail('Se esperaba un fallo simulado de almacenamiento.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado de almacenamiento.', $exception->getMessage());
        }

        $this->assertDatabaseCount('procedure_requests', 0);
        $this->assertDatabaseCount('request_documents', 0);
        $this->assertDatabaseCount('request_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
        Storage::disk(PrivateDocumentStorage::DISK)->assertMissing('requests/temp/first.pdf');
    }

    public function test_applicant_can_list_view_and_download_only_own_requests(): void
    {
        $owner = $this->applicant();
        $otherApplicant = $this->applicant('otro.solicitante@example.test');
        [$procedureType, $requirements] = $this->procedureTypeWithRequirements();

        $this->actingAs($owner)->post(route('applicant.procedure-requests.store'), [
            ...$this->baseData($procedureType),
            'documents' => [$requirements[0]->id => $this->validPdf()],
        ])->assertRedirect();
        $procedureRequest = ProcedureRequest::with('documents')->firstOrFail();
        $document = $procedureRequest->documents->firstOrFail();

        $this->get(route('applicant.procedure-requests.index'))->assertOk()->assertSee($procedureRequest->tracking_code);
        $this->get(route('applicant.procedure-requests.show', $procedureRequest))->assertOk()->assertSee($procedureRequest->subject);
        $this->get(route('applicant.procedure-requests.documents.download', [$procedureRequest, $document]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($otherApplicant)->get(route('applicant.procedure-requests.show', $procedureRequest))->assertForbidden();
        $this->get(route('applicant.procedure-requests.documents.download', [$procedureRequest, $document]))->assertForbidden();
    }

    public function test_non_applicant_role_cannot_access_request_registration(): void
    {
        $role = Role::factory()->create(['name' => 'Administrador', 'active' => true]);
        $administrator = User::factory()->for($role)->create(['active' => true]);

        $this->actingAs($administrator)->get(route('applicant.procedure-requests.index'))->assertForbidden();
        $this->get(route('applicant.procedure-requests.create'))->assertForbidden();
        $this->post(route('applicant.procedure-requests.store'), [])->assertForbidden();
    }

    /** @return array{ProcedureType, Collection<int, ProcedureRequirement>} */
    private function procedureTypeWithRequirements(int $count = 1, bool $required = true): array
    {
        $this->registeredStatus();
        $procedureType = ProcedureType::factory()->create(['active' => true]);
        $requirements = ProcedureRequirement::factory()->count($count)->for($procedureType)->create([
            'required' => $required,
            'active' => true,
        ]);

        return [$procedureType, $requirements];
    }

    /** @return array<string, mixed> */
    private function baseData(ProcedureType $procedureType): array
    {
        return [
            'procedure_type_id' => $procedureType->id,
            'subject' => 'Asunto ficticio válido',
            'description' => 'Descripción ficticia válida para registrar la solicitud.',
            'confirmation' => '1',
        ];
    }

    private function validPdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'documento-ficticio.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );
    }

    private function validPng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }

    private function registeredStatus(): Status
    {
        return Status::firstOrCreate(['code' => 'REGISTRADO'], [
            'name' => 'Registrado',
            'description' => 'Estado inicial ficticio.',
            'sort_order' => 1,
            'active' => true,
        ]);
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
}
