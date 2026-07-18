<?php

namespace App\Services;

class StructuredConditionEvaluator
{
    /** @param list<array{field:string,operator:string,value:mixed}> $conditions @param array<string, mixed> $answers */
    public function evaluate(array $conditions, array $answers): array
    {
        $unmet = [];

        foreach ($conditions as $condition) {
            $field = $condition['field'];
            if (! array_key_exists($field, $answers) || ! $this->matches($answers[$field], $condition['operator'], $condition['value'])) {
                $unmet[] = $this->message($condition);
            }
        }

        return ['eligible' => $unmet === [], 'unmet' => $unmet, 'basis' => 'declared'];
    }

    /** @param list<array{field:string,operator:string,value:mixed}> $conditions */
    public function fields(array $conditions): array
    {
        return collect($conditions)->pluck('field')->filter()->unique()->values()->all();
    }

    private function matches(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => (string) $actual === (string) $expected,
            'not_equals' => (string) $actual !== (string) $expected,
            'in' => in_array((string) $actual, array_map('strval', (array) $expected), true),
            'not_in' => ! in_array((string) $actual, array_map('strval', (array) $expected), true),
            'between' => is_numeric($actual) && count((array) $expected) === 2 && (float) $actual >= (float) $expected[0] && (float) $actual <= (float) $expected[1],
            'greater_than' => is_numeric($actual) && (float) $actual > (float) $expected,
            'greater_than_or_equal' => is_numeric($actual) && (float) $actual >= (float) $expected,
            'less_than' => is_numeric($actual) && (float) $actual < (float) $expected,
            'less_than_or_equal' => is_numeric($actual) && (float) $actual <= (float) $expected,
            'exists' => filled($actual) === (bool) $expected,
            default => false,
        };
    }

    private function message(array $condition): string
    {
        return "No se cumple la condición declarada para {$condition['field']}.";
    }
}
