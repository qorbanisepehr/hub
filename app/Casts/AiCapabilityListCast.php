<?php

namespace App\Casts;

use App\Domains\Ai\Enums\AiCapability;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * `list<AiCapability>` for `ai_models.capabilities`. The enum is the point:
 * a capability stored as free text is a capability nobody can check, and
 * the binding check (A2) is exactly what the column exists to make possible.
 *
 * The read path is tolerant on purpose and matches the other list casts in
 * this namespace: a value the enum does not know is dropped rather than
 * thrown, so a column written by a future version cannot turn a list page
 * into a 500. Writes are strict — a value that is not a capability is a
 * programming error, and silently storing `"teext"` would defeat the cast.
 *
 * @implements CastsAttributes<list<AiCapability>, mixed>
 */
class AiCapabilityListCast implements CastsAttributes
{
    /**
     * @return list<AiCapability>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return array_values(array_filter(
            array_map($this->parse(...), $this->elements($value)),
            fn (?AiCapability $capability): bool => $capability !== null,
        ));
    }

    /**
     * @param  list<AiCapability>  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $capabilities = array_map($this->stringify(...), $this->elements($value));

        return (string) json_encode(array_values(array_unique($capabilities)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function parse(mixed $item): ?AiCapability
    {
        if ($item instanceof AiCapability) {
            return $item;
        }

        return is_string($item) ? AiCapability::tryFrom($item) : null;
    }

    private function stringify(mixed $item): string
    {
        if ($item instanceof AiCapability) {
            return $item->value;
        }

        if (! is_string($item) || AiCapability::tryFrom($item) === null) {
            throw new \InvalidArgumentException(
                'AI capabilities must be '.implode(', ', array_column(AiCapability::cases(), 'value')).'.',
            );
        }

        return $item;
    }

    /**
     * @return list<mixed>
     */
    private function elements(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
