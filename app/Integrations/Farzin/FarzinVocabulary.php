<?php

namespace App\Integrations\Farzin;

use App\Contracts\External\FarzinGateway;

/**
 * Farzin source vocabulary in one place: the source name, the external
 * table names, and the mapping from boundary DTOs to the generic
 * land-instructions the Ingest sync job consumes. The vocabulary dies
 * here and in the normalizers — nothing downstream knows Farzin field
 * or table names (same stance as the old staging job, now merged into
 * the single-step ingest sync).
 *
 * @phpstan-type LandInstruction array{source_table: string, external_id: string, payload: array<string, mixed>}
 */
final class FarzinVocabulary
{
    public const SOURCE = 'farzin';

    public const TABLE_LETTERS = 'Entity_A4letter';

    public const TABLE_SESSIONS = 'Entity_heyatModire';

    /**
     * Every row the gateway currently exposes, as generic land
     * instructions. Unmappable rows are skipped at the DTO boundary —
     * a row without its primary key cannot be synced anyway and must
     * not kill the whole batch (the gateway adapter guarantees this).
     *
     * @return list<LandInstruction>
     */
    public static function landInstructions(FarzinGateway $gateway, ?string $afterLastEdit): array
    {
        $instructions = [];

        foreach ($gateway->letters($afterLastEdit) as $row) {
            $instructions[] = [
                'source_table' => self::TABLE_LETTERS,
                'external_id' => (string) $row->entityCode,
                'payload' => $row->raw,
            ];
        }

        foreach ($gateway->sessions($afterLastEdit) as $row) {
            $instructions[] = [
                'source_table' => self::TABLE_SESSIONS,
                'external_id' => (string) $row->entityCode,
                'payload' => $row->raw,
            ];
        }

        return $instructions;
    }
}
