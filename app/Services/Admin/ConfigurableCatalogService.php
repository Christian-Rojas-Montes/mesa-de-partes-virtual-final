<?php

namespace App\Services\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ConfigurableCatalogService
{
    /** @param class-string<Model> $modelClass @param array<string, mixed> $data */
    public function create(string $modelClass, array $data): Model
    {
        return DB::transaction(fn () => $modelClass::query()->create($data));
    }

    /** @param array<string, mixed> $data */
    public function update(Model $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data) {
            $model->update($data);

            return $model->refresh();
        });
    }

    public function toggle(Model $model): Model
    {
        return DB::transaction(function () use ($model) {
            $model->update(['active' => ! $model->getAttribute('active')]);

            return $model->refresh();
        });
    }
}
