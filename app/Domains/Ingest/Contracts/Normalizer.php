<?php

namespace App\Domains\Ingest\Contracts;

/**
 * One normalizer per source table: turns a raw staging payload into the
 * generic record shape. Source-specific vocabulary (field names, code
 * meanings, status words) dies here — the record layer is vocabulary-
 * free. Implementations are registered per `source` + `source_table` in
 * the Ingest registry; projections of consuming domains only know
 * `source` + `entity_type`.
 */
interface Normalizer
{
    /** The entity_type this normalizer produces (e.g. "letter", "session"). */
    public function entityType(): string;

    /**
     * Map one raw staging payload to the generic record values.
     *
     * @param  array<string, mixed>  $payload  Untouched staging payload.
     * @return array{title: ?string, date: ?string, pdate: ?string, data: array<string, mixed>}
     *
     * @throws \Throwable when the payload is not mappable (row lands as failed staging).
     */
    public function normalize(array $payload): array;
}
