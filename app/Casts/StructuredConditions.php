<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class StructuredConditions implements CastsAttributes
{
    private const OPERATORS = ['equals', 'not_equals', 'in', 'not_in', 'between', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'exists'];

    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return $value === null ? [] : (json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?: []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('Las condiciones deben ser una lista estructurada.');
        }

        foreach ($value as $condition) {
            if (! is_array($condition)
                || ! is_string($condition['field'] ?? null)
                || trim($condition['field']) === ''
                || ! in_array($condition['operator'] ?? null, self::OPERATORS, true)
                || ! array_key_exists('value', $condition)) {
                throw new InvalidArgumentException('Una condición configurada no tiene una estructura válida.');
            }
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
