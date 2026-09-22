<?php

namespace App\Support\Exports;

use Illuminate\Support\Carbon;

/**
 * Composes the download filename: `{base}-{Ymd-His}.{extension}`.
 * Today duplicated in RoleController (`Y-m-d-His`) and AuditLogController
 * (`Ymd-His`) — unified here on the shorter `Ymd-His` form.
 */
final class ExportFilename
{
    public static function make(string $base, string $extension, ?Carbon $at = null, string $format = 'Ymd-His'): string
    {
        return $base.'-'.($at ?? now())->format($format).'.'.$extension;
    }
}
