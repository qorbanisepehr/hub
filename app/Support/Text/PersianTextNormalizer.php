<?php

namespace App\Support\Text;

/**
 * Shared Persian/Arabic text normalization for all external data (A4).
 * Rewritten from TCK's `cleanText()` and kept behavior-compatible so the
 * archive reproduction (K6) yields identical output. Pure and stateless.
 */
final class PersianTextNormalizer
{
    private const ARABIC_TO_PERSIAN = [
        'ي' => 'ی',
        'ك' => 'ک',
        'ة' => 'ه',
        'أ' => 'ا',
        'إ' => 'ا',
        'ؤ' => 'و',
        'ء' => '',
        'ـ' => '',
        '‌' => '',
    ];

    public function clean(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $text = $this->extractCdataOrStripTags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(
            array_keys(self::ARABIC_TO_PERSIAN),
            array_values(self::ARABIC_TO_PERSIAN),
            $text,
        );

        $text = preg_replace('/[\x{00A0}\x{200B}-\x{200F}\x{2028}\x{2029}\x{FEFF}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([،؛:.!?\(\)\{\}\[\]])/u', '$1', $text) ?? $text;
        $text = preg_replace('/([\(\{\[])\s+/u', '$1', $text) ?? $text;
        $text = preg_replace('/\s+([\)\}\]])/u', '$1', $text) ?? $text;

        return trim($text);
    }

    private function extractCdataOrStripTags(string $text): string
    {
        if (! str_contains($text, '<![CDATA[')) {
            return strip_tags($text);
        }

        preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $text, $matches);

        return $matches[1] ?? $text;
    }
}
