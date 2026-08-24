<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Data\AuditData;
use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Persists audit records to the database.
 * Handles failure gracefully — audit errors never roll back business transactions.
 */
final class AuditRecorder
{
    public function __construct(
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    /**
     * Persist an audit record. Returns true on success, false on failure.
     * Failures are logged to the application log but never thrown.
     */
    public function persist(AuditData $data): bool
    {
        try {
            $array = $data->toArray();
            $array['old_values'] = $array['old_values'] !== null
                ? $this->sanitizer->sanitize($array['old_values'])
                : null;
            $array['new_values'] = $array['new_values'] !== null
                ? $this->sanitizer->sanitize($array['new_values'])
                : null;
            $array['metadata'] = $array['metadata'] !== null
                ? $this->sanitizer->sanitize($array['metadata'])
                : null;

            AuditLog::create($array);

            return true;
        } catch (\Throwable $e) {
            Log::error('Audit record failed', [
                'event' => $data->event,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
