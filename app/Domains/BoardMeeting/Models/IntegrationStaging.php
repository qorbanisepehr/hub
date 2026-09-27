<?php

namespace App\Domains\BoardMeeting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One raw external row in the confirmed staging/ETL pipeline. Generic by
 * design — `source`+`source_table`+`external_id` identify it, `payload`
 * holds the untouched row, `checksum` makes re-processing idempotent.
 * Domain projections consume processed/raw rows; they never see the
 * source connection.
 */
final class IntegrationStaging extends Model
{
    public const STATUS_RAW = 'raw';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'integration_staging';

    public $timestamps = false;

    protected $fillable = [
        'source',
        'source_table',
        'external_id',
        'payload',
        'checksum',
        'status',
        'error',
        'fetched_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** Canonical checksum over the raw payload content. */
    public static function checksumFor(array $payload): string
    {
        return sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
