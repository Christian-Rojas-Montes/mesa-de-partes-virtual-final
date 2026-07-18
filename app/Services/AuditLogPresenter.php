<?php

namespace App\Services;

use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\User;

class AuditLogPresenter
{
    /** @return array<string, string> */
    public function entities(): array
    {
        return [
            ProcedureRequest::class => 'Solicitud',
            User::class => 'Usuario',
            Area::class => 'Área',
            ProcedureType::class => 'Tipo de trámite',
            ProcedureRequirement::class => 'Requisito',
        ];
    }

    public function action(string $action): string
    {
        return [
            'submitted' => 'Solicitud registrada',
            'review_started' => 'Revisión iniciada',
            'validated' => 'Solicitud validada',
            'observed' => 'Solicitud observada',
            'rejected' => 'Solicitud rechazada',
            'correction_submitted' => 'Subsanación presentada',
            'derived' => 'Expediente derivado',
            'rederived' => 'Derivación corregida',
            'derivation_received' => 'Expediente recibido',
            'attention_started' => 'Atención iniciada',
            'attention_action' => 'Acción de atención registrada',
            'response_registered' => 'Respuesta registrada',
            'request_finalized' => 'Expediente finalizado',
            'created' => 'Registro creado',
            'updated' => 'Registro actualizado',
            'activated' => 'Registro activado',
            'deactivated' => 'Registro desactivado',
        ][$action] ?? str($action)->replace('_', ' ')->title()->toString();
    }

    public function entity(string $type): string
    {
        return $this->entities()[$type] ?? class_basename($type);
    }

    /** @param array<string, mixed>|null $details
     *  @return array<string, scalar|null>
     */
    public function safeDetails(?array $details): array
    {
        $safe = [];

        foreach ($details ?? [] as $key => $value) {
            if (preg_match('/password|token|secret|(^|_)content($|_)|path|checksum|stored_name|file_name/i', (string) $key)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($this->safeDetails($value) as $nestedKey => $nestedValue) {
                    $safe[$key.'.'.$nestedKey] = $nestedValue;
                }
            } elseif (is_bool($value)) {
                $safe[(string) $key] = $value ? 'Sí' : 'No';
            } elseif (is_scalar($value) || $value === null) {
                $safe[(string) $key] = $value;
            }
        }

        return $safe;
    }
}
