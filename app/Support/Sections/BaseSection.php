<?php

namespace App\Support\Sections;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

abstract class BaseSection implements SectionDefinition
{
    /**
     * Validate section data for the given mode.
     */
    public function validateData(array $data, string $mode = self::MODE_STRUCTURAL): Validator
    {
        $validator = ValidatorFactory::make(
            [$this->key() => $data],
            $this->prefixRules($this->rulesFor($mode), $this->key()),
        );

        $validator->after(function (Validator $validator) use ($data, $mode): void {
            $this->afterValidation($validator, $data, $mode);
        });

        return $validator;
    }

    /**
     * Rules for the given mode.
     */
    public function rulesFor(string $mode): array
    {
        return match ($mode) {
            self::MODE_COMPLETION => $this->completionRules(),
            default => $this->structuralRules(),
        };
    }

    /**
     * No document requirements by default.
     *
     * @return array<string, array<string, mixed>>
     */
    public function documentRequirements(): array
    {
        return [];
    }

    /**
     * Cross-field validation hook. Override in concrete sections.
     */
    protected function afterValidation(Validator $validator, array $data, string $mode): void
    {
        // Override for cross-field checks (e.g. date ranges, min age).
    }

    /**
     * Prefix rule keys AND conditional rule field references with the section key.
     *
     * Rules like required_if, required_unless, required_with, etc. reference
     * sibling fields. After wrapping data as [sectionKey => $data], those
     * references must also be prefixed.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function prefixRules(array $rules, string $prefix): array
    {
        $conditionalMethods = [
            'required_if',
            'required_unless',
            'required_with',
            'required_with_all',
            'required_without',
            'required_without_all',
        ];

        $prefixed = [];
        foreach ($rules as $field => $rule) {
            $prefixedField = "{$prefix}.{$field}";
            $prefixed[$prefixedField] = is_array($rule)
                ? array_map(
                    fn (mixed $item): mixed => is_string($item)
                        ? $this->prefixConditionalReferences($item, $prefix, $conditionalMethods)
                        : $item,
                    $rule,
                )
                : $this->prefixConditionalReferences($rule, $prefix, $conditionalMethods);
        }

        return $prefixed;
    }

    /**
     * Prefix field references inside conditional rules.
     *
     * @param  string[]  $conditionalMethods
     */
    protected function prefixConditionalReferences(string $rule, string $prefix, array $conditionalMethods): string
    {
        // Handle pipe-separated rules: "nullable|required_if:field,value|..."
        $pipes = explode('|', $rule);
        $result = [];

        foreach ($pipes as $pipe) {
            $trimmed = trim($pipe);
            $colonPos = strpos($trimmed, ':');

            if ($colonPos !== false) {
                $method = substr($trimmed, 0, $colonPos);
                $params = substr($trimmed, $colonPos + 1);

                if (in_array($method, $conditionalMethods)) {
                    // First param is the field reference — prefix it
                    $paramParts = explode(',', $params, 2);
                    $paramParts[0] = "{$prefix}.{$paramParts[0]}";
                    $trimmed = $method.':'.implode(',', $paramParts);
                }
            }

            $result[] = $trimmed;
        }

        return implode('|', $result);
    }

    /**
     * Reject a row whose "to" date precedes its "from" date.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function assertDateRangeOrder(Validator $validator, array $rows, string $dateLabel, string $messageKey): void
    {
        foreach ($rows as $index => $row) {
            $from = $row['from'] ?? null;
            $to = $row['to'] ?? null;

            if ($from && $to && $to < $from) {
                $validator->errors()->add(
                    "{$this->key()}.{$dateLabel}.{$index}.to",
                    __($messageKey),
                );
            }
        }
    }
}
