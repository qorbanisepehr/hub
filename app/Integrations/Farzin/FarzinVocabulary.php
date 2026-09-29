<?php

namespace App\Integrations\Farzin;

use App\Contracts\External\FarzinGateway;

/**
 * Farzin source vocabulary in one place: the source name, the external
 * table names, and the mapping from raw gateway rows to the generic land
 * instructions the Ingest sync job consumes. The vocabulary dies here and
 * in the normalizers — nothing downstream knows Farzin field or table
 * names.
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
     * instructions.
     *
     * @return list<LandInstruction>
     */
    public static function landInstructions(FarzinGateway $gateway, ?string $afterLastEdit): array
    {
        return [
            ...self::instructionsFor(self::TABLE_LETTERS, $gateway->letters($afterLastEdit)),
            ...self::instructionsFor(self::TABLE_SESSIONS, $gateway->sessions($afterLastEdit)),
        ];
    }

    /**
     * A row without its `EntityCode` is skipped at this boundary: it has
     * no identity, so it cannot be synced at all and must not kill the
     * batch. It re-appears unchanged on the next run.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<LandInstruction>
     */
    private static function instructionsFor(string $table, array $rows): array
    {
        $instructions = [];

        foreach ($rows as $row) {
            $code = $row['EntityCode'] ?? null;

            if ($code === null || $code === '') {
                continue;
            }

            $instructions[] = [
                'source_table' => $table,
                'external_id' => (string) $code,
                'payload' => $row,
            ];
        }

        return $instructions;
    }
}
