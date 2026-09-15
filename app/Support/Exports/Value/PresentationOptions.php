<?php

namespace App\Support\Exports\Value;

/**
 * How the kernel should shape a file for human readers: header language and
 * the calendar/digit conventions. Everything defaults to the byte-stable
 * machine form so existing exporters (Visio chart, audit log) are unaffected
 * unless the request opts in.
 */
final class PresentationOptions
{
    public function __construct(
        /**
         * 'key' writes the machine header (dotted keys, Visio's Name/Manager);
         * 'label' writes the localized label from the exporter's catalog.
         */
        public readonly string $headers = 'key',

        /**
         * Date cell calendar and shape: 'gregorian' → `Y-m-d` (import-safe),
         * 'persian' → Jalali `1404/03/25`, 'both' → the exporter emits a
         * Jalali sibling column next to each Gregorian one.
         */
        public readonly string $calendar = 'gregorian',

        /**
         * Digit glyphs for ALL formatted numbers — dates and numeric cells
         * alike (one switch, per the export-dialog decision): 'latin' or
         * 'persian'. Persian digits read naturally but Excel treats such
         * cells as text.
         */
        public readonly string $digits = 'latin',

        /**
         * Whether repeater fields declared by the exporter go to their own
         * detail sheets (one row per entry, parent-key addressed). xlsx
         * hosts them; other formats silently fall back to the base sheet.
         */
        public readonly bool $detailSheets = false,
    ) {}

    public function wantsPresentation(): bool
    {
        return $this->headers === 'label'
            || $this->calendar !== 'gregorian'
            || $this->digits !== 'latin'
            || $this->detailSheets;
    }
}
