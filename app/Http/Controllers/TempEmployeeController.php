<?php

namespace App\Http\Controllers;

use App\Models\TempEmployee;
use App\Services\TempEmployeeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Read-mostly endpoints for the temporary file-explorer tool. Intentionally
 * gated by plain Sanctum authentication (no dedicated permissions) — this is
 * throwaway tooling and must not be shipped to production as-is.
 */
class TempEmployeeController extends Controller
{
    public function __construct(
        private readonly TempEmployeeSyncService $syncService,
    ) {}

    /**
     * Paginated + searchable list. `?search=` matches personnel code,
     * national id, first, or last name; `?page=` / `?per_page=` paginate.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $paginator = TempEmployee::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where(fn ($q) => $q
                    ->where('personnel_code', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")),
            )
            ->orderBy('id')
            ->paginate(
                perPage: min((int) $request->query('per_page', 15) ?: 15, 100),
                page: (int) $request->query('page', 1) ?: 1,
            );

        return response()->json([
            'data' => $paginator->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Sync on-disk folders into temp_employees using the sync service's
     * folder-name pattern. Returns created/updated counts plus skipped
     * folders whose names did not match.
     */
    public function sync(): JsonResponse
    {
        return response()->json(['data' => $this->syncService->sync()]);
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
