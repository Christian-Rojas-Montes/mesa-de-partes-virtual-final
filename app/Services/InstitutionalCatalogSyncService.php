<?php

namespace App\Services;

use App\Models\InstitutionalCatalogSyncRecord;
use App\Models\InstitutionalCatalogVersion;
use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedurePrerequisite;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InstitutionalCatalogSyncService
{
    public function synchronize(bool $apply = false, ?string $requestedVersion = null): array
    {
        $catalog = config('institutional-catalog');
        if ($requestedVersion && $requestedVersion !== $catalog['version']) {
            throw new \InvalidArgumentException("La versión {$requestedVersion} no está disponible. Versión incorporada: {$catalog['version']}.");
        }
        $report = ['version' => $catalog['version'], 'mode' => $apply ? 'apply' : 'dry-run', 'created' => [], 'changed' => [], 'unchanged' => [], 'conflicts' => []];
        $operation = function () use ($catalog, $apply, &$report): void {
            $modalities = [];
            foreach ($catalog['modalities'] ?? [] as $values) {
                $key = $values['code'];
                $modalities[$key] = $this->sync(PresentationModality::class, 'modality', $key, $values, $apply, $report, fn () => PresentationModality::where('code', $key)->first());
            }
            $categories = [];
            foreach ($catalog['categories'] as $values) {
                $key = $values['code'];
                $categories[$key] = $this->sync(ProcedureCategory::class, 'category', $key, $values + ['description' => 'Categoría del catálogo institucional.', 'active' => true], $apply, $report, fn () => ProcedureCategory::where('code', $key)->first());
            }
            $procedureByCode = [];
            $pendingPrerequisites = [];
            foreach ($catalog['procedures'] as $categoryCode => $procedures) {
                foreach ($procedures as $position => $definition) {
                    $fields = $definition['fields'] ?? [];
                    $requirements = $definition['requirements'] ?? [];
                    $variants = $definition['variants'] ?? [];
                    $prerequisites = $definition['prerequisites'] ?? [];
                    $modalityCode = $definition['presentation_modality_code'] ?? null;
                    unset($definition['fields'], $definition['requirements'], $definition['variants'], $definition['prerequisites'], $definition['presentation_modality_code']);
                    if ($modalityCode) {
                        $definition['presentation_modality_id'] = ($modalities[$modalityCode] ?? null)?->id;
                    }
                    $definition += ['procedure_category_id' => $categories[$categoryCode]?->id, 'sort_order' => ($position + 1) * 10, 'attention_days' => 0, 'requires_payment' => false, 'currency' => 'PEN'];
                    $procedure = $this->sync(ProcedureType::class, 'procedure', $definition['code'], $definition, $apply, $report, fn () => ProcedureType::where('code', $definition['code'])->first());
                    if (! $procedure) {
                        continue;
                    }
                    $procedureByCode[$definition['code']] = $procedure;
                    foreach ($prerequisites as $prerequisite) {
                        $pendingPrerequisites[] = [$procedure, $definition['code'], $prerequisite];
                    }
                    foreach ($fields as $index => $field) {
                        $values = $field + ['procedure_type_id' => $procedure->id, 'sort_order' => ($index + 1) * 10, 'active' => true];
                        $this->sync(ProcedureDynamicField::class, 'field', $definition['code'].':'.$field['key'], $values, $apply, $report);
                    }
                    foreach ($requirements as $index => $requirement) {
                        $values = $requirement + ['description' => 'Documento requerido por la fuente institucional.', 'procedure_type_id' => $procedure->id, 'sort_order' => ($index + 1) * 10, 'active' => true, 'copy_count' => 1];
                        $this->sync(ProcedureRequirement::class, 'requirement', $definition['code'].':'.($index + 1), $values, $apply, $report);
                    }
                    foreach ($variants as $variantIndex => $variantDefinition) {
                        $variantRequirements = $variantDefinition['requirements'] ?? [];
                        unset($variantDefinition['requirements']);
                        $variantValues = $variantDefinition + ['procedure_type_id' => $procedure->id, 'sort_order' => ($variantIndex + 1) * 10, 'active' => true, 'reception_open' => true, 'allows_digital_registration' => true, 'requires_physical_delivery' => false, 'requires_payment' => false, 'currency' => 'PEN'];
                        $variant = $this->sync(ProcedureVariant::class, 'variant', $definition['code'].':'.$variantDefinition['code'], $variantValues, $apply, $report);
                        if (! $variant) {
                            continue;
                        }
                        foreach ($variantRequirements as $index => $requirement) {
                            $values = $requirement + ['description' => 'Documento requerido por la fuente institucional.', 'procedure_type_id' => $procedure->id, 'procedure_variant_id' => $variant->id, 'sort_order' => ($index + 1) * 10, 'active' => true, 'copy_count' => 1];
                            $this->sync(ProcedureRequirement::class, 'requirement', $definition['code'].':'.$variantDefinition['code'].':'.($index + 1), $values, $apply, $report);
                        }
                    }
                }
            }
            foreach ($pendingPrerequisites as [$procedure, $procedureCode, $prerequisite]) {
                $requiredCode = $prerequisite['required_procedure_code'] ?? null;
                $key = $prerequisite['key'];
                unset($prerequisite['key'], $prerequisite['required_procedure_code']);
                $values = $prerequisite + ['procedure_type_id' => $procedure->id, 'required_procedure_type_id' => $requiredCode ? ($procedureByCode[$requiredCode] ?? null)?->id : null, 'required' => true, 'active' => true, 'sort_order' => 10];
                $this->sync(ProcedurePrerequisite::class, 'prerequisite', $procedureCode.':'.$key, $values, $apply, $report);
            }
            if ($apply && empty($report['conflicts'])) {
                InstitutionalCatalogVersion::updateOrCreate(['version' => $catalog['version']], ['checksum' => $this->checksum($catalog), 'summary' => collect($report)->only(['created', 'changed', 'unchanged'])->all(), 'applied_at' => now()]);
            }
        };
        $apply ? DB::transaction($operation) : $operation();

        return $report;
    }

    private function sync(string $modelClass, string $type, string $key, array $values, bool $apply, array &$report, ?callable $unmanagedLookup = null): ?Model
    {
        $record = InstitutionalCatalogSyncRecord::where(['entity_type' => $type, 'stable_key' => $key])->first();
        $model = $record ? $modelClass::find($record->entity_id) : ($unmanagedLookup ? $unmanagedLookup() : null);
        if ($model && ! $record) {
            $report['conflicts'][] = "{$type}:{$key} ya existe sin control institucional; no se sobrescribió.";

            return $model;
        }
        if ($record && ! $model) {
            $report['conflicts'][] = "{$type}:{$key} fue eliminado localmente; requiere revisión.";

            return null;
        }
        if ($record && $this->managed($model, array_keys($record->managed_values)) !== $record->managed_values) {
            $report['conflicts'][] = "{$type}:{$key} tiene modificaciones locales; no se sobrescribió.";

            return $model;
        }
        $normalized = $this->normalize($values);
        if (! $model) {
            $report['created'][] = "{$type}:{$key}";
            if (! $apply) {
                return new $modelClass($values);
            }
            $model = $modelClass::create($values);
        } elseif ($this->managed($model, array_keys($normalized)) === $normalized) {
            $report['unchanged'][] = "{$type}:{$key}";
        } else {
            $report['changed'][] = "{$type}:{$key}";
            if ($apply) {
                $model->update($values);
            }
        }
        if ($apply) {
            $managed = $this->managed($model->fresh(), array_keys($normalized));
            InstitutionalCatalogSyncRecord::updateOrCreate(['entity_type' => $type, 'stable_key' => $key], ['entity_id' => $model->id, 'checksum' => $this->checksum($managed), 'managed_values' => $managed]);
        }

        return $model;
    }

    private function managed(Model $model, array $keys): array
    {
        return $this->normalize($model->only($keys));
    }

    private function normalize(array $values): array
    {
        ksort($values);

        return array_map(function ($value) {
            if (is_array($value)) {
                return array_is_list($value) ? array_map(fn ($item) => is_array($item) ? $this->normalize($item) : $item, $value) : $this->normalize($value);
            }

            if (is_bool($value) || $value === null) {
                return $value;
            }
            if (is_numeric($value)) {
                return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
            }

            return $value;
        }, json_decode(json_encode($values, JSON_UNESCAPED_UNICODE), true));
    }

    private function checksum(array $values): string
    {
        return hash('sha256', json_encode($this->normalize($values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
