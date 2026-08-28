<?php

namespace App\Domains\TempEmployees\Controllers;

use App\Domains\TempEmployees\Models\TempEmployee;
use App\Domains\TempEmployees\Services\TempEmployeeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Read-mostly endpoints for the temporary file-explorer tool. Intentionally
 * gated by plain Sanctum authentication (no dedicated permissions) — this is
 * throwaway tooling and must not be shipped to production as-is.
 */
class TempEmployeeController
{
    public function __construct(
        private readonly TempEmployeeSyncService $syncService,
    ) {}

    /**
     * Paginated + searchable list. `?search=` matches personnel code,
     * national id, first, or last name; `?page=` / `?per_page=` paginate.
     *
     * Each temp record is enriched with the matching `employees` row (keyed by
     * personnel code) so the tool can show the real employee when one exists,
     * falling back to the temp record fields. The linked system user's active
     * role and role list are also attached when available. This is a read-only
     * lookup that stays entirely within this temporary tooling and does not
     * modify any Employee- or Authorization-domain file.
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

        $employeesByCode = DB::table('employees')
            ->whereIn('personnel_code', $paginator->getCollection()->pluck('personnel_code'))
            ->select([
                'personnel_code',
                'user_id',
                'first_name',
                'last_name',
                'id_number',
                'email',
                'mobile',
                'gender',
                'employment_status',
                'employment_type',
                'hire_date',
            ])
            ->get()
            ->keyBy('personnel_code');

        $userIds = $employeesByCode
            ->filter(fn ($employee) => $employee->user_id !== null)
            ->pluck('user_id')
            ->unique()
            ->values();

        $activeRoleByUser = collect();
        $rolesByUser = collect();

        if ($userIds->isNotEmpty()) {
            $activeRoleByUser = DB::table('users')
                ->whereIn('id', $userIds)
                ->pluck('active_role_id', 'id');

            $rolesByUser = DB::table('roles')
                ->join('role_user', 'roles.id', '=', 'role_user.role_id')
                ->whereIn('role_user.user_id', $userIds)
                ->where('roles.is_active', 1)
                ->where('roles.type', 'organization')
                ->select('role_user.user_id', 'roles.id', 'roles.display_name')
                ->get()
                ->groupBy('user_id');
        }

        $data = $paginator->getCollection()
            ->map(function (TempEmployee $temp) use ($employeesByCode, $activeRoleByUser, $rolesByUser) {
                $employee = $employeesByCode->get($temp->personnel_code);

                if ($employee === null || $employee->user_id === null) {
                    return [
                        ...$temp->attributesToArray(),
                        'employee' => $employee !== null ? [
                            'personnel_code' => $employee->personnel_code,
                            'first_name' => $employee->first_name,
                            'last_name' => $employee->last_name,
                            'id_number' => $employee->id_number,
                            'email' => $employee->email,
                            'mobile' => $employee->mobile,
                            'gender' => $employee->gender,
                            'employment_status' => $employee->employment_status,
                            'employment_type' => $employee->employment_type,
                            'hire_date' => $employee->hire_date,
                            'roles' => [],
                        ] : null,
                    ];
                }

                $activeRoleId = (int) $activeRoleByUser->get($employee->user_id);

                $roles = ($rolesByUser->get($employee->user_id) ?? collect())
                    ->map(fn ($role): array => [
                        'id' => (int) $role->id,
                        'display_name' => $role->display_name,
                        'active' => (int) $role->id === $activeRoleId,
                    ])
                    ->values()
                    ->all();

                return [
                    ...$temp->attributesToArray(),
                    'employee' => [
                        'personnel_code' => $employee->personnel_code,
                        'first_name' => $employee->first_name,
                        'last_name' => $employee->last_name,
                        'id_number' => $employee->id_number,
                        'email' => $employee->email,
                        'mobile' => $employee->mobile,
                        'gender' => $employee->gender,
                        'employment_status' => $employee->employment_status,
                        'employment_type' => $employee->employment_type,
                        'hire_date' => $employee->hire_date,
                        'roles' => $roles,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
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
     * Stream one file for the lightbox/preview (inline) or, when
     * `?download=1`, serve it as an attachment download. The requested path is
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

        $disposition = $request->boolean('download')
            ? 'attachment'
            : 'inline';

        return response()->file($full, [
            'Content-Type' => mime_content_type($full) ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.basename($full).'"',
        ]);
    }

    /**
     * Replace an existing image file with an edited upload. Mirrors the path
     * resolution + traversal guard of `file()`; the uploaded image overwrites
     * the file at `path`. Only image uploads are accepted. Returns the
     * refreshed file node for the updated entry.
     */
    public function replaceFile(Request $request, TempEmployee $employee): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:20480'],
            'path' => ['required', 'string', 'max:1024'],
        ]);

        $disk = Storage::disk('local');
        $base = realpath($disk->path($employee->filesDirectory()));

        abort_if($base === false, 404);

        $full = realpath($base.DIRECTORY_SEPARATOR.$validated['path']);

        abort_if(
            $full === false || ! str_starts_with($full, $base.DIRECTORY_SEPARATOR),
            422,
        );
        abort_unless(is_file($full), 404);

        /** @var UploadedFile $uploaded */
        $uploaded = $request->file('file');

        file_put_contents($full, (string) $uploaded->getContent());

        return response()->json([
            'data' => [
                'name' => basename($full),
                'path' => $validated['path'],
                'type' => 'file',
                'size' => filesize($full) ?: null,
                'mime' => mime_content_type($full) ?: null,
                'modified_at' => date('Y-m-d H:i:s', (int) filemtime($full)),
            ],
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
