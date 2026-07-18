<?php

namespace Tests\Feature\Database;

use App\Models\Area;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialCatalogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_role_and_optional_area(): void
    {
        $role = Role::factory()->create();
        $area = Area::factory()->create();
        $internalUser = User::factory()->for($role)->for($area)->create();
        $applicant = User::factory()->for($role)->create(['area_id' => null]);

        $this->assertTrue($internalUser->role->is($role));
        $this->assertTrue($internalUser->area->is($area));
        $this->assertTrue($role->users->contains($internalUser));
        $this->assertTrue($area->users->contains($internalUser));
        $this->assertNull($applicant->area);
    }

    public function test_procedure_type_has_requirements(): void
    {
        $procedureType = ProcedureType::factory()->create();
        $requirements = ProcedureRequirement::factory()
            ->count(2)
            ->for($procedureType)
            ->create();

        $this->assertCount(2, $procedureType->requirements);
        $this->assertTrue($requirements->every(
            fn (ProcedureRequirement $requirement): bool => $requirement->procedureType->is($procedureType)
        ));
    }

    public function test_valid_catalog_and_user_records_can_be_created(): void
    {
        $role = Role::factory()->create(['active' => true]);
        $area = Area::factory()->create(['active' => true]);
        $user = User::factory()->for($role)->for($area)->create(['active' => true]);
        $procedureType = ProcedureType::factory()->create(['attention_days' => 5]);
        $requirement = ProcedureRequirement::factory()->for($procedureType)->create();
        $status = Status::factory()->create(['sort_order' => 1]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role_id' => $role->id, 'area_id' => $area->id]);
        $this->assertDatabaseHas('procedure_requirements', [
            'id' => $requirement->id,
            'procedure_type_id' => $procedureType->id,
        ]);
        $this->assertDatabaseHas('statuses', ['id' => $status->id, 'sort_order' => 1]);
        $this->assertCount(1, Role::active()->get());
        $this->assertCount(1, Area::active()->get());
        $this->assertCount(1, User::active()->get());
    }

    public function test_unique_constraints_reject_duplicate_values(): void
    {
        $role = Role::factory()->create();
        $area = Area::factory()->create();
        $user = User::factory()->for($role)->create();
        $procedureType = ProcedureType::factory()->create();
        $status = Status::factory()->create();

        $this->assertUniqueConstraint(fn () => Role::factory()->create(['name' => $role->name]));
        $this->assertUniqueConstraint(fn () => Area::factory()->create(['code' => $area->code]));
        $this->assertUniqueConstraint(fn () => Area::factory()->create(['name' => $area->name]));
        $this->assertUniqueConstraint(fn () => User::factory()->for($role)->create([
            'document_number' => $user->document_number,
        ]));
        $this->assertUniqueConstraint(fn () => User::factory()->for($role)->create(['email' => $user->email]));
        $this->assertUniqueConstraint(fn () => ProcedureType::factory()->create(['code' => $procedureType->code]));
        $this->assertUniqueConstraint(fn () => ProcedureType::factory()->create(['name' => $procedureType->name]));
        $this->assertUniqueConstraint(fn () => Status::factory()->create(['code' => $status->code]));
        $this->assertUniqueConstraint(fn () => Status::factory()->create(['name' => $status->name]));
    }

    private function assertUniqueConstraint(callable $operation): void
    {
        try {
            $operation();
            $this->fail('La base de datos aceptó un valor que debía ser único.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
