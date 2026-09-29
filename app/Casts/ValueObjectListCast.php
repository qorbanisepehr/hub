<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared JSONB ↔ typed-list behaviour: one direction converts each raw
 * element into a value object, the other converts it back. Subclasses
 * only name their value class and its two conversions, so the tolerant
 * read path (a malformed column degrades to an empty list instead of
 * throwing on a GET) exists exactly once.
 *
 * @template TValue of object
 *
 * @implements CastsAttributes<list<TValue>, mixed>
 */
abstract class ValueObjectListCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $data
     * @return TValue
     */
    abstract protected function makeValue(array $data): object;

    /**
     * @param  TValue  $value
     * @return array<string, mixed>
     */
    abstract protected function valueToArray(object $value): array;

    /**
     * @return list<TValue>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return array_map($this->makeValue(...), $this->elements($value));
    }

    /**
     * Eloquent folds a class cast's return value into `$attributes` as-is
     * (an array return is read as the attribute map itself), so the raw
     * column value is what belongs here — the encoded JSON, and `null`
     * for an empty list rather than a stored `[]`.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $values = $this->normalize($value);

        if ($values === []) {
            return null;
        }

        $rows = array_map(fn (object $item) => $this->valueToArray($item), $values);

        return json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<TValue>
     */
    private function normalize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            return $this->elements($value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_map(
            fn (mixed $item) => is_object($item) ? $item : $this->makeValue((array) $item),
            array_values($value),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function elements(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
}
