<?php

namespace App\Domains\Audit\Services;

/**
 * Redacts sensitive fields from audit data before storage.
 * Single source of truth for field-level sanitization rules.
 */
final class SensitiveDataSanitizer
{
    /** @var list<string> */
    private readonly array $sensitiveFields;

    public function __construct()
    {
        $this->sensitiveFields = config('audit.sensitive_fields', []);
    }

    /**
     * Redact sensitive values from an array of data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        return $this->redact($data);
    }

    /**
     * Recursively redact sensitive fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->redact($value);
            } elseif ($this->isSensitive($key)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a field name is sensitive.
     */
    private function isSensitive(string $field): bool
    {
        $lower = strtolower($field);

        foreach ($this->sensitiveFields as $sensitive) {
            if ($lower === strtolower($sensitive)) {
                return true;
            }
        }

        return false;
    }
}
