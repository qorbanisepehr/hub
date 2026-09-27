<?php

namespace App\Domains\BoardMeeting\Services;

/**
 * Meeting-number extraction from approval document filenames (A11, TCK
 * `createMeetingNumber` parity). Filename contract: a 4-digit approval
 * number, an optional 1-digit ordinal, and the meeting date in parens —
 * e.g. `…1234-2(1404-06-15)….pdf` → «2/1234/ص/1404». Multiple documents
 * join with `-`.
 */
final class MeetingNumberService
{
    private const FILENAME_SHAPE = '/.+(\d{4})-?(\d{1})?\((\d{4})-(\d{2})-(\d{2})\).+/';

    /**
     * @param  list<string>|null  $filenames
     */
    public function fromFilenames(?array $filenames): string
    {
        $number = '';

        foreach ($filenames ?? [] as $filename) {
            if (preg_match(self::FILENAME_SHAPE, (string) $filename, $m) !== 1) {
                continue;
            }

            $number .= ($number !== '' ? '-' : '')
                .($m[2] !== '' && $m[2] !== null
                    ? "{$m[2]}/{$m[1]}/ص/{$m[3]}"
                    : "{$m[1]}/ص/{$m[3]}");
        }

        return $number;
    }
}
