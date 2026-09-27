<?php

namespace App\Integrations\Farzin;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * The real read-only Farzin source (K1): plain SELECTs over the configured
 * `sqlsrv` connection — no writes, no schema assumptions beyond the two
 * tables the sync reads. Activation is configuration only
 * (FARZIN_DRIVER=sqlsrv + connection credentials in .env).
 */
final class SqlsrvFarzinSource implements FarzinGatewayContract
{
    /** TCK parity: only the secretariat role's letters are synced. */
    private const A4_CREATOR_ROLE_ID = 2880;

    public function __construct(
        private readonly string $connection = 'sqlsrv',
    ) {}

    public function letters(?string $afterLastEdit): array
    {
        return $this->connection()
            ->table('eOrgan_Kani_Mes.dbo.Entity_A4letter')
            ->where('CreatorRoleID', self::A4_CREATOR_ROLE_ID)
            ->when($afterLastEdit !== null, fn ($q) => $q->where('LastEditDate', '>', $afterLastEdit))
            ->orderBy('EntityCode')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function sessions(?string $afterLastEdit): array
    {
        return $this->connection()
            ->table('eOrgan_Kani_Mes.dbo.Entity_heyatModire')
            ->when($afterLastEdit !== null, fn ($q) => $q->where('LastEditDate', '>', $afterLastEdit))
            ->orderBy('EntityCode')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }
}
