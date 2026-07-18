<?php

namespace Tests\Feature\Database;

use App\Models\Area;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededDevelopmentDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_catalogs_and_development_users_are_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['Administrador', 'Mesa de Partes', 'Responsable de área', 'Solicitante'],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertSame(
            ['Registrado', 'En revisión', 'Observado', 'Derivado', 'En atención', 'Atendido', 'Rechazado', 'Finalizado'],
            Status::query()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame(5, ProcedureType::query()->count());
        $this->assertSame(10, ProcedureRequirement::query()->count());
        $this->assertSame(4, Area::query()->count());
        $this->assertSame(4, User::query()->count());

        $expectedRoles = [
            'solicitante@example.test' => 'Solicitante',
            'mesa.partes@example.test' => 'Mesa de Partes',
            'responsable.area@example.test' => 'Responsable de área',
            'administrador@example.test' => 'Administrador',
        ];

        foreach ($expectedRoles as $email => $roleName) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($roleName, $user->role->name);
            $this->assertStringEndsWith('@example.test', $user->email);
        }

        $this->assertNull(User::query()->where('email', 'solicitante@example.test')->firstOrFail()->area_id);
        $this->assertNotNull(User::query()->where('email', 'mesa.partes@example.test')->firstOrFail()->area_id);
        $this->assertNotNull(User::query()->where('email', 'responsable.area@example.test')->firstOrFail()->area_id);
        $this->assertNotNull(User::query()->where('email', 'administrador@example.test')->firstOrFail()->area_id);
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Role::query()->count());
        $this->assertSame(4, Area::query()->count());
        $this->assertSame(8, Status::query()->count());
        $this->assertSame(5, ProcedureType::query()->count());
        $this->assertSame(10, ProcedureRequirement::query()->count());
        $this->assertSame(4, User::query()->count());
    }
}
