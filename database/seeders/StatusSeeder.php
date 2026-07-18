<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'REGISTRADO', 'name' => 'Registrado', 'description' => 'La solicitud fue enviada correctamente.'],
            ['code' => 'EN_REVISION', 'name' => 'En revisión', 'description' => 'La documentación está siendo verificada.'],
            ['code' => 'OBSERVADO', 'name' => 'Observado', 'description' => 'Existen requisitos que deben ser corregidos.'],
            ['code' => 'DERIVADO', 'name' => 'Derivado', 'description' => 'El expediente fue enviado al área competente.'],
            ['code' => 'EN_ATENCION', 'name' => 'En atención', 'description' => 'El área responsable está procesando el trámite.'],
            ['code' => 'ATENDIDO', 'name' => 'Atendido', 'description' => 'La respuesta fue registrada.'],
            ['code' => 'RECHAZADO', 'name' => 'Rechazado', 'description' => 'El trámite no procede y se consignó la justificación.'],
            ['code' => 'FINALIZADO', 'name' => 'Finalizado', 'description' => 'El trámite concluyó y conserva su historial.'],
        ];

        foreach ($statuses as $index => $status) {
            Status::query()->updateOrCreate(
                ['code' => $status['code']],
                [...$status, 'sort_order' => $index + 1, 'active' => true],
            );
        }
    }
}
