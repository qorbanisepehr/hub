<?php

namespace App\Domains\Letters\Source;

/**
 * Marker mirroring the TCK source's BoardMeeting FQCN
 * (App\Domains\Letters\Models\BoardMeeting) — `taggables.taggable_type`
 * in the source archive stores that string. The import command maps
 * source pivots to hub meetings by the meeting's `pdate`, using this
 * constant only for the source-side filter. Kept isolated from the hub's
 * real BoardMeeting domain on purpose.
 */
final class TckBoardMeeting
{
    public const SOURCE_CLASS = 'App\Domains\Letters\Models\BoardMeeting';
}
