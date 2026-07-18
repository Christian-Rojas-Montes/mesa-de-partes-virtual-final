<?php

namespace App\Services;

use App\Enums\TitleModality;
use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcedureRequestSubmissionService
{
    public function __construct(
        private readonly TrackingCodeGenerator $codeGenerator,
        private readonly PrivateDocumentStorage $documentStorage,
        private readonly InternalNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int|string, UploadedFile>  $files
     */
    public function submit(User $user, array $data, array $files, array $context = []): ProcedureRequest
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($user, $data, $files, $context, &$storedPaths) {
                $status = Status::active()->where('code', 'REGISTRADO')->firstOrFail();
                $variant = $context['variant'] ?? null;
                $responses = $context['responses'] ?? [];
                $snapshot = isset($context['service']) ? $context['service']->snapshot($context['type'], $variant, $context['fields'], $context['requirements'], $responses) : null;
                $modality = $snapshot['modality_code'] ?? 'digital';
                $procedureRequest = ProcedureRequest::query()->create([
                    'user_id' => $user->id,
                    'procedure_type_id' => $data['procedure_type_id'],
                    'procedure_variant_id' => $variant?->id,
                    'status_id' => $status->id,
                    'tracking_code' => $this->codeGenerator->generate(),
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'dynamic_responses' => $responses ?: null,
                    'configuration_snapshot' => $snapshot,
                    'submitted_at' => now(),
                ]);

                if (($context['type']->code ?? null) === 'TITLE_PROF_TECH') {
                    $modality = match ($variant?->code) {
                        'APPLICATION_WORK' => TitleModality::APPLICATION_WORK,
                        'PROFESSIONAL_EXAM' => TitleModality::PROFESSIONAL_EXAM,
                        default => throw new \InvalidArgumentException('La modalidad de titulación no es válida.'),
                    };
                    app(TitleProcessService::class)->create($procedureRequest, $user, $modality, $responses, $responses['intento_convocatoria'] ?? null);
                }

                foreach ($files as $requirementId => $file) {
                    $metadata = $this->documentStorage->store($file, $procedureRequest);
                    $storedPaths[] = $metadata['path'];
                    $procedureRequest->documents()->create([
                        'procedure_requirement_id' => $requirementId === 'general' ? null : (int) $requirementId,
                        ...$metadata,
                    ]);
                }

                $procedureRequest->histories()->create([
                    'status_id' => $status->id,
                    'user_id' => $user->id,
                    'action' => match ($modality) {
                        'hybrid' => 'pending_physical_delivery', 'in_person' => 'pre_registration_created', default => 'registered'
                    },
                    'description' => match ($modality) {
                        'hybrid' => 'La solicitud fue registrada y está pendiente de presentación física.', 'in_person' => 'Se creó una preinscripción; la presentación oficial debe realizarse en Mesa de Partes.', default => 'La solicitud fue presentada digitalmente.'
                    },
                ]);

                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'submitted',
                    'auditable_type' => $procedureRequest->getMorphClass(),
                    'auditable_id' => $procedureRequest->id,
                    'details' => [
                        'tracking_code' => $procedureRequest->tracking_code,
                        'procedure_type_id' => $procedureRequest->procedure_type_id,
                        'procedure_variant_id' => $procedureRequest->procedure_variant_id,
                        'document_count' => count($files),
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $this->notifications->dispatch(
                    $procedureRequest,
                    InternalNotificationService::REGISTERED,
                    match ($modality) {
                        'hybrid' => 'Tu solicitud fue registrada. Presenta la documentación física indicada.', 'in_person' => 'Tu preinscripción fue creada. La presentación oficial se realiza en Mesa de Partes.', default => 'Tu solicitud digital fue registrada correctamente.'
                    },
                    'submission',
                );

                return $procedureRequest;
            });
        } catch (Throwable $exception) {
            $this->documentStorage->delete($storedPaths);
            throw $exception;
        }
    }
}
