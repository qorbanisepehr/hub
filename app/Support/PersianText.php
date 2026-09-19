<?php

namespace App\Support;

/**
 * Persian text folding for matching user-typed words against stored
 * vocabulary: Unicode NFC composition, Arabic yeh/kaf → Persian yeh/kaf
 * (the two variants Excel and Arabic keyboards produce), and zero-width
 * marks dropped. Digits (Persian + Arabic-Indic) fold to latin.
 */
final class PersianText
{
    /** Persian + Arabic-Indic digit glyphs → latin (public: callers fold digits unconditionally). */
    public const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        // Arabic-Indic digits (Excel Arabic locale):
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /** Arabic → Persian letter variants + zero-width marks to drop. */
    private const LETTER_FOLDS = [
        'ي' => 'ی', // Arabic yeh → Persian yeh
        'ك' => 'ک', // Arabic kaf → Persian kaf
        'ﻻ' => 'لا', // Arabic lam-alef ligature
        "\u{200C}" => '', // ZWNJ
        "\u{200F}" => '', // RLM
        "\u{200E}" => '', // LRM
        "\u{FEFF}" => '', // BOM-as-ZWSP
    ];

    /**
     * The canonical comparison form of a human-typed Persian word: NFC,
     * letter variants folded, zero-width marks gone, digits latin, edges
     * trimmed, case-folded.
     */
    public static function fold(string $value): string
    {
        $value = normalizer_is_normalized($value)
            ? $value
            : (string) normalizer_normalize($value, \Normalizer::FORM_C);

        $value = strtr($value, self::LETTER_FOLDS);
        $value = strtr($value, self::DIGITS);

        return mb_strtolower(trim($value));
    }
}
