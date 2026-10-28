<?php

namespace App\Support\Audit;

/**
 * The audit diff contract every auditing domain shares (ADR-008): compare
 * two attribute arrays and report ONLY the changed leaf keys as parallel
 * 'old'/'new' maps.
 *
 * Nested arrays (JSONB columns) are flattened to dotted leaf key paths
 * first, so an edit inside a list of attachments records exactly which
 * leaf changed instead of the whole column array — a one-field change
 * must not dump a whole section. Flat top-level columns keep their key.
 *
 * Living in @see \App\Support means no domain imports another domain for
 * this one algorithm; ADR-008's "whole-model comparison elsewhere should
 * reuse the same helper" is the reason this is shared rather than copied
 * a second time.
 */
final class AttributeDiff
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function diff(array $old, array $new): array
    {
        $oldFlat = self::flatten($old);
        $newFlat = self::flatten($new);

        $keys = array_unique(array_merge(array_keys($oldFlat), array_keys($newFlat)));

        $oldChanged = [];
        $newChanged = [];

        foreach ($keys as $key) {
            $oldValue = $oldFlat[$key] ?? null;
            $newValue = $newFlat[$key] ?? null;

            if ($oldValue !== $newValue) {
                $oldChanged[$key] = $oldValue;
                $newChanged[$key] = $newValue;
            }
        }

        return ['old' => $oldChanged, 'new' => $newChanged];
    }

    /**
     * Flatten a nested attribute array into dotted key paths. Scalar/missing
     * leaves keep their key; nested arrays flatten to "<parent>.<child>...".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function flatten(array $values, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flattened = array_replace($flattened, self::flatten($value, $path));
            } else {
                $flattened[$path] = $value;
            }
        }

        return $flattened;
    }
}
