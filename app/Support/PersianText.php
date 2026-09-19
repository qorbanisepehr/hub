<?php

namespace App\Support;

/**
 * Shared Persian-text canonicalization for anything that compares Persian
 * strings written by humans (import vocabulary matching today, search or
 * duplicate detection later). Excel and Arabic keyboards silently produce
 * different Unicode forms of the same word — Arabic ي/ك, ZWNJ, decomposed
 * NFC, Persian digits — and exact matching fails without folding first.
 */
final class PersianText
{
    /** Persian digit glyphs → latin, applied before any comparison. */
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** Arabic letterform variants → their Persian forms. */
    private const LETTERS = [
        'ي' => 'ی', // Arabic yeh → Persian yeh
        'ى' => 'ی', // Alef maksura → Persian yeh
        'ك' => 'ک', // Arabic kaf → Persian kaf
        'أ' => 'ا', // Alef with hamza above → plain alef
        'إ' => 'ا', // Alef with hamza below → plain alef
        'آ' => 'آ', // kept — آ is a distinct Persian letter
    ];

    /** Zero-width / direction marks that never change a word's identity. */
    private const INVISIBLES = ["\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}", "\u{FEFF}"];

    /**
     * Digits only — for value positions where letters carry meaning (a
     * date, an ID, a phone number) and the letter forms must survive.
     */
    public static function foldDigits(string $value): string
    {
        return strtr($value, self::DIGITS);
    }

    public static function fold(string $value): string
    {
        $value = normalizer_is_normalized($value, \Normalizer::NFC)
            ? $value
            : (string) normalizer_normalize($value, \Normalizer::NFC);

        $value = strtr($value, self::DIGITS);
        $value = strtr($value, self::LETTERS);

        foreach (self::INVISIBLES as $mark) {
            $value = str_replace($mark, '', $value);
        }

        // Comparison semantics include case-insensitivity (the pre-fold
        // matcher lowercased both sides) — Persian has no case, but latin
        // values like «a+» vs «A+» do.
        $value = mb_strtolower($value, 'UTF-8');

        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }
}
