<?php

namespace App\Support\Imports\Contract;

/**
 * Optional definition capability: normalize one raw cell VALUE before
 * validation. Import files are filled by humans, so a cell may carry the
 * human word («مرد», «بله») where the stored form keeps the stable key
 * (`male`, `true`). The definition owns the vocabulary — the kernel never
 * interprets it. Values the definition cannot translate pass through
 * unchanged (the section rules then accept or reject them as-is).
 */
interface NormalizesValues
{
    /**
     * The stored value for the given raw cell, or null to keep the raw
     * value untouched.
     *
     * @param  string  $columnKey  The file's column key (dotted).
     * @param  string  $value  The raw cell value as the file carried it.
     */
    public function normalizedValue(string $columnKey, string $value): ?string;
}
