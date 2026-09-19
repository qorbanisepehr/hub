<?php

namespace App\Domains\Employee\Imports;

use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Imports\Contract\NormalizesValues;

/**
 * Human-word → stored-value normalization for employee import cells. The
 * file is filled by humans: option columns may carry the Persian display
 * label («مرد» instead of `male`), boolean columns the localized word
 * («بله»/«خیر»), and digits may arrive in Persian glyphs. Normalization
 * runs BEFORE validation so the section rules always judge the stored
 * form; anything the vocabulary doesn't know passes through unchanged and
 * is accepted or rejected by the rules exactly as before.
 */
final class EmployeeValueNormalizer implements NormalizesValues
{
    /** Persian digit glyphs folded to latin before value resolution. */
    private const PERSIAN_DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** The localized boolean words a presented export writes. */
    private const BOOLEAN_WORDS = [
        'بله' => '1', 'خیر' => '0',
        'yes' => '1', 'no' => '0',
    ];

    public function __construct(
        private readonly EmployeeExporter $exporter,
        private readonly FormOptionService $formOptions,
    ) {}

    public function normalizedValue(string $columnKey, string $value): ?string
    {
        // Persian glyph digits fold to latin first — labels never contain
        // them, but numbers (código, national ID, phone) typed on a Persian
        // keyboard do.
        $folded = strtr($value, self::PERSIAN_DIGITS);

        if ($folded !== $value) {
            return $folded;
        }

        // Booleans: the presented export writes the localized word.
        $boolean = self::BOOLEAN_WORDS[mb_strtolower(trim($value))] ?? null;

        if ($boolean !== null) {
            return $boolean;
        }

        // Option columns: label → stable value via the group map. The
        // exporter owns the column→group vocabulary (one source, shared
        // with the export presenter).
        $group = EmployeeExporter::OPTION_GROUPS[$columnKey] ?? null;

        if ($group !== null) {
            return $this->formOptions->labelToValue($group, $value);
        }

        // Fixed enums: invert the exporter's own label map (official ↔ رسمی).
        $maps = trans('employee.exports.options');

        if (is_array($maps) && is_array($maps[$columnKey] ?? null)) {
            $inverted = array_flip(array_map('strval', $maps[$columnKey]));

            return $inverted[$value] ?? null;
        }

        return null;
    }
}
