<?php

namespace App\Services;

use App\Enums\ProcedureAvailability;
use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhysicalProcedureRegistrationService
{
    public function __construct(private readonly TrackingCodeGenerator $codes, private readonly DynamicProcedureFormService $forms, private readonly PublicProcedureCatalogService $catalog, private readonly PhysicalReceptionService $receptions, private readonly InternalNotificationService $notifications) {}

    public function register(User $actor, array $data): ProcedureRequest
    {
        $createdApplicant = false;
        $request = DB::transaction(function () use ($actor, $data, &$createdApplicant) {
            [$applicant, $createdApplicant] = $this->applicant($data);
            $type = ProcedureType::query()->where('active', true)->with(['presentationModality', 'category', 'variants.presentationModality'])->lockForUpdate()->findOrFail($data['procedure_type_id']);
            if ($this->catalog->availability($type) !== ProcedureAvailability::AVAILABLE) {
                throw ValidationException::withMessages(['procedure_type_id' => 'El trámite no está disponible para recepción.']);
            }
            $variant = null;
            if ($type->variants->isNotEmpty()) {
                $variant = $type->variants->firstWhere('id', $data['procedure_variant_id'] ?? null);
                if (! $variant || $this->catalog->availability($variant) !== ProcedureAvailability::AVAILABLE) {
                    throw ValidationException::withMessages(['procedure_variant_id' => 'La variante no corresponde al trámite o no está disponible.']);
                }
            } elseif (filled($data['procedure_variant_id'] ?? null)) {
                throw ValidationException::withMessages(['procedure_variant_id' => 'El trámite no utiliza variantes.']);
            }

            $requirements = $this->forms->requirements($type, $variant)->where('requires_physical_submission', true)->values();
            $received = collect($data['received_documents'] ?? [])->filter(fn ($item) => (bool) ($item['received'] ?? false));
            $unknown = $received->keys()->diff($requirements->pluck('id')->map(fn ($id) => (string) $id));
            if ($unknown->isNotEmpty()) {
                throw ValidationException::withMessages(['received_documents' => 'Se indicó un documento que no corresponde a los requisitos físicos aplicables.']);
            }
            $presented = $received->map(function ($item, $id) use ($requirements) {
                $requirement = $requirements->firstWhere('id', (int) $id);

                return ['requirement_id' => $requirement->id, 'name' => $requirement->name, 'presentation' => $item['presentation'], 'quantity' => (int) $item['quantity']];
            })->values();
            $pending = $requirements->where('required', true)->reject(fn ($requirement) => $received->has((string) $requirement->id) || $received->has($requirement->id))->values();
            $snapshot = $this->forms->snapshot($type, $variant, collect(), $requirements, []);
            $snapshot['submission_channel'] = 'in_person';
            $snapshot['pending_requirements'] = $pending->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all();

            $status = Status::active()->where('code', 'REGISTRADO')->firstOrFail();
            $procedureRequest = ProcedureRequest::query()->create(['user_id' => $applicant->id, 'procedure_type_id' => $type->id, 'procedure_variant_id' => $variant?->id, 'status_id' => $status->id, 'tracking_code' => $this->codes->generate(), 'subject' => 'Presentación presencial: '.$type->name, 'description' => ($data['observations'] ?? null) ?: 'Expediente presentado físicamente en Mesa de Partes.', 'configuration_snapshot' => $snapshot, 'submitted_at' => $data['received_at']]);
            $procedureRequest->histories()->create(['status_id' => $status->id, 'user_id' => $actor->id, 'action' => 'physical_case_registered', 'description' => 'Mesa de Partes registró el expediente presentado físicamente.']);
            AuditLog::query()->create(['user_id' => $actor->id, 'action' => 'physical_case_registered', 'auditable_type' => $procedureRequest->getMorphClass(), 'auditable_id' => $procedureRequest->id, 'details' => ['applicant_id' => $applicant->id, 'applicant_created' => $createdApplicant, 'procedure_type_id' => $type->id, 'pending_requirement_count' => $pending->count()], 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
            $this->receptions->confirm($procedureRequest, $actor, ['received_at' => $data['received_at'], 'folio_count' => $data['folio_count'] ?? null, 'document_count' => $presented->sum('quantity'), 'presented_documents' => $presented->all(), 'observations' => $data['observations'] ?? null, 'receiving_area_id' => $data['receiving_area_id'] ?? $actor->area_id, 'receipt_number' => $procedureRequest->tracking_code, 'verification_result' => $pending->isEmpty() ? 'complete' : 'incomplete']);
            $safePending = $pending->where('sensitive', false)->pluck('name');
            $message = "Expediente {$procedureRequest->tracking_code} de {$type->name}: estado {$status->name}.".($safePending->isEmpty() ? ' No hay documentos públicos pendientes.' : ' Pendientes: '.$safePending->implode(', ').'.');
            $this->notifications->dispatch($procedureRequest, 'physical_case_registered', $message, 'physical-case-registration');

            return $procedureRequest;
        });

        if ($createdApplicant && $request->user->email) {
            Password::sendResetLink(['email' => $request->user->email]);
        }

        return $request;
    }

    private function applicant(array $data): array
    {
        if (filled($data['existing_user_id'] ?? null)) {
            $user = User::query()->with('role')->lockForUpdate()->findOrFail($data['existing_user_id']);
            if ($user->role?->name !== 'Solicitante') {
                throw ValidationException::withMessages(['existing_user_id' => 'La cuenta seleccionada no pertenece a un solicitante.']);
            }

            return [$user, false];
        }
        if (User::query()->where('document_number', $data['document_number'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['document_number' => 'Ya existe una cuenta con este documento; debes vincularla.']);
        }
        if (filled($data['email'] ?? null) && User::query()->where('email', $data['email'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['email' => 'Ya existe una cuenta con este correo; debes vincularla.']);
        }
        if (filled($data['student_code'] ?? null) && User::query()->where('student_code', $data['student_code'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['student_code' => 'Ya existe una cuenta con este código de estudiante.']);
        }
        $role = Role::query()->where('name', 'Solicitante')->where('active', true)->firstOrFail();
        $user = User::query()->create(['role_id' => $role->id, 'area_id' => null, 'document_type' => $data['document_type'], 'document_number' => $data['document_number'], 'student_code' => $data['student_code'] ?? null, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'] ?: null, 'phone' => $data['phone'] ?? null, 'academic_program' => $data['academic_program'], 'academic_condition' => $data['academic_condition'], 'account_claim_pending' => true, 'password' => Str::random(64), 'active' => filled($data['email'] ?? null)]);

        return [$user, true];
    }
}
