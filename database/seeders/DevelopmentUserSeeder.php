<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    private const DEVELOPMENT_PASSWORD = 'Demo1234!';

    public function run(): void
    {
        $users = [
            [
                'email' => 'solicitante@example.test',
                'role' => 'Solicitante',
                'area' => null,
                'document_number' => 'DEV-SOL-001',
                'first_name' => 'Cuenta',
                'last_name' => 'Solicitante',
            ],
            [
                'email' => 'mesa.partes@example.test',
                'role' => 'Mesa de Partes',
                'area' => 'MP-SA',
                'document_number' => 'DEV-MP-001',
                'first_name' => 'Cuenta',
                'last_name' => 'Mesa de Partes',
            ],
            [
                'email' => 'responsable.area@example.test',
                'role' => 'Responsable de área',
                'area' => 'CDSI',
                'document_number' => 'DEV-RA-001',
                'first_name' => 'Cuenta',
                'last_name' => 'Responsable',
            ],
            [
                'email' => 'administrador@example.test',
                'role' => 'Administrador',
                'area' => 'UADM',
                'document_number' => 'DEV-ADM-001',
                'first_name' => 'Cuenta',
                'last_name' => 'Administradora',
            ],
        ];

        foreach ($users as $data) {
            $role = Role::query()->where('name', $data['role'])->firstOrFail();
            $areaId = $data['area'] === null
                ? null
                : Area::query()->where('code', $data['area'])->firstOrFail()->id;

            $user = User::query()->firstOrNew(['email' => $data['email']]);
            $user->fill([
                'role_id' => $role->id,
                'area_id' => $areaId,
                'document_type' => 'FICTICIO',
                'document_number' => $data['document_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => null,
                'active' => true,
            ]);
            $user->email_verified_at = '2026-01-01 00:00:00';

            if (! $user->exists) {
                $user->password = Hash::make(self::DEVELOPMENT_PASSWORD);
            }

            $user->save();
        }
    }
}
