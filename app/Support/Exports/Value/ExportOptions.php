<?php

namespace App\Support\Exports\Value;

/**
 * Byte-level options of the file format: encoding concerns only.
 * Data quirks (target-software vocabulary) belong to adapters, not here.
 */
final class ExportOptions
{
    public function __construct(
        /**
         * Emit a UTF-8 BOM before the first row. Excel needs it for Persian
         * text; Visio's import must not receive one. Default: off — matches
         * today's audit/role-chart output.
         */
        public readonly bool $bom = false,

        /**
         * Guard cells against CSV/Excel formula injection by prefixing values
         * starting with `= + - @` (also after leading whitespace). Keep on for
         * any file a human will open in Excel.
         */
        public readonly bool $formulaGuard = false,
    ) {}
}
