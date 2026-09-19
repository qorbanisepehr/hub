<?php

namespace App\Support\Imports;

use App\Support\Imports\Contract\RowsPersister;
use App\Support\Imports\Contract\RowValidator;
use App\Support\Imports\Value\ImportColumn;

/**
 * One importable entity's contract: what the file may contain, what each
 * row must fill, how rows are validated, and how they land. The controller
 * and the kernel speak only to this contract — a new entity is a class
 * plus one registry line.
 */
interface ImportDefinition
{
    /**
     * The registry key (e.g. 'employees') — routes and the UI address the
     * entity by it.
     */
    public function name(): string;

    /**
     * Human label for the entity picker and reports (translation-driven).
     */
    public function label(): string;

    /**
     * The accepted columns in template order.
     *
     * @return list<ImportColumn>
     */
    public function acceptedColumns(): array;

    /**
     * Column keys the FILE must carry (header-level check).
     *
     * @return list<string>
     */
    public function requiredTemplateColumns(): array;

    /**
     * Column keys a ROW must fill (row-level check).
     *
     * @return list<string>
     */
    public function requiredRowKeys(): array;

    public function validator(): RowValidator;

    public function persister(): RowsPersister;
}
