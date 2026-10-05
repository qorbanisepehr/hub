<?php

namespace App\Domains\Ai\Value;

use App\Domains\Ai\Enums\AiCapability;
use InvalidArgumentException;

/**
 * One model a provider is known to serve, as it lives inside a provider
 * setting rather than as a row of its own.
 *
 * Its name is passed in rather than stored, because the name is the key it sits
 * under in the setting's `models` map. Holding a second copy of it inside the
 * value would be the duplication this design exists to remove: two places to
 * keep in step, and jsonb silently keeps the last of a duplicated key, so the
 * disagreement would not even be visible.
 *
 * `capabilities` is a typed list so that binding an agent to a model can be
 * checked (A2) rather than discovered on the first paid request. Reads drop a
 * value the enum does not know so a setting written by a future version cannot
 * turn a list page into a 500; writes reject one, because storing `"teext"`
 * would defeat the point of having an enum.
 */
final readonly class AiModel
{
    /**
     * @param  list<AiCapability>  $capabilities
     */
    public function __construct(
        public string $name,
        public ?string $label,
        public array $capabilities,
        public bool $isActive,
    ) {}

    /**
     * What to show a person: the label if there is one, the name otherwise.
     *
     * A separate answer rather than a `label` that falls back to the name,
     * because "this model has no label" and "this model's label happens to be
     * its name" are different facts and a client that wants to show the label
     * alone has to be able to ask.
     */
    public function displayLabel(): string
    {
        return $this->label ?? $this->name;
    }

    /**
     * @return array{label: ?string, capabilities: list<string>, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'capabilities' => array_map(
                static fn (AiCapability $capability): string => $capability->value,
                $this->capabilities,
            ),
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws InvalidArgumentException when the entry is not the shape it claims to be
     */
    public static function fromStored(string $name, array $value): self
    {
        $label = $value['label'] ?? null;

        if (! is_string($label) && $label !== null) {
            throw new InvalidArgumentException(sprintf(
                'The label of model [%s] must be a string or absent, %s given.',
                $name,
                get_debug_type($label),
            ));
        }

        $isActive = $value['is_active'] ?? null;

        if (! is_bool($isActive)) {
            throw new InvalidArgumentException(sprintf(
                'The is_active flag of model [%s] must be a boolean, %s given.',
                $name,
                get_debug_type($isActive),
            ));
        }

        return new self(
            name: $name,
            label: $label,
            capabilities: self::capabilities($name, $value['capabilities'] ?? []),
            isActive: $isActive,
        );
    }

    /**
     * @return list<AiCapability>
     */
    private static function capabilities(string $name, mixed $given): array
    {
        if (! is_array($given)) {
            throw new InvalidArgumentException(sprintf(
                'The capabilities of model [%s] must be a list, %s given.',
                $name,
                get_debug_type($given),
            ));
        }

        $capabilities = [];

        foreach ($given as $case) {
            if (! is_string($case)) {
                throw new InvalidArgumentException(sprintf(
                    'A capability of model [%s] must be a string, %s given.',
                    $name,
                    get_debug_type($case),
                ));
            }

            $capability = AiCapability::tryFrom($case);

            if ($capability === null) {
                throw new InvalidArgumentException(sprintf(
                    '[%s] is not a capability model [%s] can have. Known: %s.',
                    $case,
                    $name,
                    implode(', ', array_column(AiCapability::cases(), 'value')),
                ));
            }

            $capabilities[] = $capability;
        }

        return array_values(array_unique($capabilities, SORT_REGULAR));
    }
}
