<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            [
                'code' => 'MP-SA',
                'name' => 'Mesa de Partes y Secretaría Académica',
                'description' => 'Área ficticia de recepción y revisión documentaria.',
            ],
            [
                'code' => 'UA',
                'name' => 'Unidad Académica',
                'description' => 'Área ficticia responsable de trámites académicos.',
            ],
            [
                'code' => 'UADM',
                'name' => 'Unidad Administrativa',
                'description' => 'Área ficticia responsable de gestiones administrativas.',
            ],
            [
                'code' => 'CDSI',
                'name' => 'Coordinación de Desarrollo de Sistemas de Información',
                'description' => 'Área ficticia de coordinación académica especializada.',
            ],
        ];

        foreach ($areas as $area) {
            Area::query()->updateOrCreate(
                ['code' => $area['code']],
                [...$area, 'active' => true],
            );
        }
    }
}
