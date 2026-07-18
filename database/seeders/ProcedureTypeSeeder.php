<?php

namespace Database\Seeders;

use App\Models\ProcedureType;
use Illuminate\Database\Seeder;

class ProcedureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $procedureTypes = [
            [
                'code' => 'SOL-GEN',
                'name' => 'Solicitud general',
                'description' => 'Presentación de una petición general ficticia.',
                'attention_days' => 10,
                'requirements' => [
                    ['name' => 'Solicitud firmada', 'description' => 'Documento ficticio que detalla la petición.', 'required' => true],
                    ['name' => 'Documento de sustento', 'description' => 'Sustento ficticio relacionado con la petición.', 'required' => false],
                ],
            ],
            [
                'code' => 'CONST-EST',
                'name' => 'Constancia de estudios',
                'description' => 'Solicitud ficticia de constancia de estudios.',
                'attention_days' => 5,
                'requirements' => [
                    ['name' => 'Documento de identidad ficticio', 'description' => 'Referencia ficticia para identificar al solicitante.', 'required' => true],
                    ['name' => 'Periodo académico', 'description' => 'Información ficticia del periodo solicitado.', 'required' => true],
                ],
            ],
            [
                'code' => 'REP-NOT',
                'name' => 'Reporte de notas',
                'description' => 'Solicitud ficticia de reporte académico.',
                'attention_days' => 5,
                'requirements' => [
                    ['name' => 'Documento de identidad ficticio', 'description' => 'Referencia ficticia para identificar al solicitante.', 'required' => true],
                    ['name' => 'Periodo académico', 'description' => 'Información ficticia del periodo del reporte.', 'required' => true],
                ],
            ],
            [
                'code' => 'LIC-RES-MAT',
                'name' => 'Licencia o reserva de matrícula',
                'description' => 'Solicitud ficticia de licencia o reserva académica.',
                'attention_days' => 10,
                'requirements' => [
                    ['name' => 'Solicitud sustentada', 'description' => 'Documento ficticio que explica el motivo de la solicitud.', 'required' => true],
                    ['name' => 'Documento de sustento', 'description' => 'Evidencia ficticia relacionada con el motivo declarado.', 'required' => true],
                ],
            ],
            [
                'code' => 'CARTA-PP',
                'name' => 'Carta para prácticas profesionales',
                'description' => 'Solicitud ficticia de carta de presentación para prácticas.',
                'attention_days' => 7,
                'requirements' => [
                    ['name' => 'Datos de la organización receptora', 'description' => 'Información ficticia de la organización destinataria.', 'required' => true],
                    ['name' => 'Ficha de prácticas', 'description' => 'Ficha ficticia con información básica de las prácticas.', 'required' => true],
                ],
            ],
        ];

        foreach ($procedureTypes as $data) {
            $requirements = $data['requirements'];
            unset($data['requirements']);

            $procedureType = ProcedureType::query()->updateOrCreate(
                ['code' => $data['code']],
                [...$data, 'active' => true],
            );

            foreach ($requirements as $requirement) {
                $procedureType->requirements()->updateOrCreate(
                    ['name' => $requirement['name']],
                    [...$requirement, 'active' => true],
                );
            }
        }
    }
}
