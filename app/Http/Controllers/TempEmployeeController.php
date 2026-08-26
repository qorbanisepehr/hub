<?php

namespace App\Http\Controllers;

use App\Models\TempEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Read-only endpoints for the temporary file-explorer tool. Intentionally
 * gated by plain Sanctum authentication (no dedicated permissions) — this is
 * throwaway tooling and must not be shipped to production as-is.
 */
class TempEmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TempEmployee::query()
                ->orderBy('id')
                ->get(['id', 'personnel_code', 'id_number', 'first_name', 'last_name']),
        ]);
    }

    public function tree(TempEmployee $employee): JsonResponse
    {
        $disk = Storage::disk('local');
        $base = $employee->filesDirectory();

        if (! $disk->exists($base)) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->scan($disk->path($base), ''),
        ]);
    }

    /**
     * Stream one file inline for the lightbox/preview. The requested path is
     * resolved against the real filesystem and must stay inside the
     * employee's folder — anything else is a 404, never a leak.
     */
    public function file(Request $request, TempEmployee $employee): BinaryFileResponse
    {
        $relative = (string) $request->query('path', '');
        $disk = Storage::disk('local');
        $base = realpath($disk->path($employee->filesDirectory()));

        abort_if($base === false, 404);

        $full = realpath($base.DIRECTORY_SEPARATOR.$relative);

        abort_if(
            $full === false || ! str_starts_with($full, $base.DIRECTORY_SEPARATOR),
            404,
        );

        abort_unless(is_file($full), 404);

        return response()->file($full, [
            'Content-Type' => mime_content_type($full) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.basename($full).'"',
        ]);
    }

    /**
     * Recursive directory listing. Directories sort before files, both
     * case-insensitively by name.
     *
     * @return list<array{name: string, path: string, type: string, size: int|null, mime: string|null, modified_at: string|null}>
     */
    private function scan(string $absoluteBase, string $relativePrefix): array
    {
        $entries = [];

        foreach (scandir($absoluteBase) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $full = $absoluteBase.DIRECTORY_SEPARATOR.$name;
            $path = $relativePrefix === '' ? $name : $relativePrefix.'/'.$name;

            if (is_dir($full)) {
                $entries[] = [
                    'name' => $name,
                    'path' => $path,
                    'type' => 'dir',
                    'size' => null,
                    'mime' => null,
                    'modified_at' => date('Y-m-d H:i:s', (int) filemtime($full)),
                ];

                array_push($entries, ...$this->scan($full, $path));

                continue;
            }

            $entries[] = [
                'name' => $name,
                'path' => $path,
                'type' => 'file',
                'size' => filesize($full) ?: null,
                'mime' => mime_content_type($full) ?: null,
                'modified_at' => date('Y-m-d H:i:s', (int) filemtime($full)),
            ];
        }

        usort($entries, fn (array $a, array $b): int => [$a['type'], strtolower($a['name'])]
            <=> [$b['type'], strtolower($b['name'])]);

        return $entries;
    }
}
