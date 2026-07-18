<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Solicitante', 'description' => 'Presenta y consulta sus trámites.'],
            ['name' => 'Mesa de Partes', 'description' => 'Revisa, observa y deriva solicitudes.'],
            ['name' => 'Responsable de área', 'description' => 'Atiende los trámites asignados a su área.'],
            ['name' => 'Administrador', 'description' => 'Administra usuarios, catálogos y parámetros.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role['name']],
                [...$role, 'active' => true],
            );
        }
    }
}
