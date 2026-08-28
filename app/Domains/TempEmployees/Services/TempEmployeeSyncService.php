<?php

namespace App\Domains\TempEmployees\Services;

use App\Domains\TempEmployees\Models\TempEmployee;
use Illuminate\Support\Facades\Storage;

/**
 * Scans the temp-files root and upserts one TempEmployee per folder whose
 * name matches the configured pattern.
 *
 * The pattern is a named-capture regex kept as an overridable property so
 * the real on-disk naming convention can change without touching logic.
 * Current convention: "{personnel-code} - {first-name} {last-name}".
 */
class TempEmployeeSyncService
{
    /**
     * Folder-name pattern. Named captures: code, first, last.
     */
    public string $folderPattern = '/^(?<code>[^\s-]+)\s+-\s+(?<first>.+?)\s+(?<last>[^-]+)$/u';

    /**
     * Storage-relative root that holds the per-employee folders.
     */
    public string $root = 'temp-files';

    /**
     * @return array{created: int, updated: int, skipped: list<string>}
     */
    public function sync(): array
    {
        $disk = Storage::disk('local');
        $result = ['created' => 0, 'updated' => 0, 'skipped' => []];

        foreach ($disk->directories($this->root) as $directory) {
            $folder = basename($directory);

            if (preg_match($this->folderPattern, $folder, $m) !== 1) {
                $result['skipped'][] = $folder;

                continue;
            }

            $employee = TempEmployee::query()->updateOrCreate(
                ['personnel_code' => $m['code']],
                [
                    'first_name' => trim($m['first']),
                    'last_name' => trim($m['last']),
                    // Point at the REAL on-disk folder so tree/file endpoints
                    // read from it instead of the plain-code convention.
                    'files_directory' => "{$this->root}/{$folder}",
                ],
            );

            if ($employee->wasRecentlyCreated) {
                $result['created']++;
            } elseif ($employee->wasChanged()) {
                $result['updated']++;
            }
        }

        return $result;
    }
}
