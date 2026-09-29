<?php

namespace App\Integrations\Farzin;

use App\Contracts\External\FarzinGateway;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The real read-only Farzin source: plain SELECTs over the configured
 * `sqlsrv` connection — no writes, no schema assumptions beyond the two
 * tables the sync reads. Activation is configuration only
 * (FARZIN_DRIVER=sqlsrv + connection credentials in .env).
 */
final class SqlsrvFarzinSource implements FarzinGateway
{
    public function __construct(
        private readonly string $connection = 'sqlsrv',
    ) {}

    public function letters(?string $afterLastEdit = null): array
    {
        return $this->select('eOrgan_Kani_Mes.dbo.Entity_A4letter', $afterLastEdit, function ($query) {
            $query->whereIn('CreatorRoleID', $this->letterCreatorRoleIds());
        });
    }

    public function sessions(?string $afterLastEdit = null): array
    {
        return $this->select('eOrgan_Kani_Mes.dbo.Entity_heyatModire', $afterLastEdit);
    }

    /**
     * The roles whose A4 letters belong to the registry. Defaults to the
     * secretariat only (TCK parity); widening it to other roles that also
     * file board invitations is a config change, not a code change.
     *
     * @return list<int>
     */
    private function letterCreatorRoleIds(): array
    {
        return array_values(array_map(
            'intval',
            (array) config('integrations.farzin.letter_creator_role_ids', [2880]),
        ));
    }

    /**
     * @param  callable(Builder): void|null  $constrain
     * @return list<array<string, mixed>>
     */
    private function select(string $table, ?string $afterLastEdit, ?callable $constrain = null): array
    {
        return $this->connection()
            ->table($table)
            ->when($afterLastEdit !== null, fn ($q) => $q->where('LastEditDate', '>', $afterLastEdit))
            ->when($constrain !== null, fn ($q) => $constrain($q))
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
