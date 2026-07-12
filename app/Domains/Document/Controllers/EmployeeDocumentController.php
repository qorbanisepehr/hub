<?php

namespace App\Domains\Document\Controllers;

use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\BulkDownloadDocumentRequest;
use App\Domains\Document\Requests\BulkStoreEmployeeDocumentRequest;
use App\Domains\Document\Requests\StoreEmployeeDocumentRequest;
use App\Domains\Document\Requests\ZipStoreEmployeeDocumentRequest;
use App\Domains\Document\Resources\DocumentResource;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class EmployeeDocumentController
{
    public function index(Employee $employee): AnonymousResourceCollection
    {
        $documents = $employee->documents()
            ->with(['category', 'uploader', 'documentable'])
            ->latest()
            ->get();

        return DocumentResource::collection($documents);
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): DocumentResource
    {
        $file = $request->file('file');

        $categorySlug = DocumentCategory::where('id', $request->document_category_id)->value('slug');
        $path = $file->store(
            $employee->personnel_code.'/documents/'.$categorySlug,
            config('documents.storage_disk'),
        );

        $document = $employee->documents()->create([
            'document_category_id' => $request->document_category_id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $request->notes,
            'uploaded_by' => $request->user()?->id,
        ]);

        GenerateDocumentThumbnail::dispatch($document);

        $document->load(['category', 'uploader']);

        return new DocumentResource($document);
    }

    public function bulkStore(BulkStoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $categoryId = $request->document_category_id;
        $categorySlug = DocumentCategory::where('id', $categoryId)->value('slug');
        $disk = config('documents.storage_disk');
        $userId = $request->user()?->id;
        $notes = $request->notes;

        $uploaded = [];
        $failed = [];
        $skipped = [];

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();

            $exists = $employee->documents()
                ->where('document_category_id', $categoryId)
                ->where('original_name', $originalName)
                ->exists();

            if ($exists) {
                $skipped[] = ['name' => $originalName, 'reason' => 'duplicate'];

                continue;
            }

            $path = $file->store(
                $employee->personnel_code.'/documents/'.$categorySlug,
                $disk,
            );

            $document = $employee->documents()->create([
                'document_category_id' => $categoryId,
                'original_name' => $originalName,
                'stored_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'notes' => $notes,
                'uploaded_by' => $userId,
            ]);

            GenerateDocumentThumbnail::dispatch($document);

            $document->load(['category', 'uploader']);

            $uploaded[] = (new DocumentResource($document))->resolve();
        }

        return response()->json([
            'data' => [
                'uploaded' => $uploaded,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
        ]);
    }

    public function bulkDownload(BulkDownloadDocumentRequest $request, Employee $employee): StreamedResponse
    {
        $disk = config('documents.storage_disk');
        $documentIds = $request->document_ids;

        $query = $employee->documents()->with('category');

        if (! empty($documentIds)) {
            $query->whereIn('id', $documentIds);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            abort(404);
        }

        $zip = new ZipArchive;
        $tempFile = tempnam(sys_get_temp_dir(), 'documents_zip_');

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip file');
        }

        $usedPaths = [];

        foreach ($documents as $document) {
            $categorySlug = $document->category?->slug ?? 'uncategorized';
            $filename = $document->original_name;
            $zipPath = $categorySlug.'/'.$filename;

            if (isset($usedPaths[$zipPath])) {
                $usedPaths[$zipPath]++;
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $suffix = $ext ? '.'.$ext : '';
                $zipPath = $categorySlug.'/'.$name.'-'.$usedPaths[$zipPath].$suffix;
            } else {
                $usedPaths[$zipPath] = 1;
            }

            if (Storage::disk($disk)->exists($document->stored_path)) {
                $content = Storage::disk($disk)->get($document->stored_path);
                $zip->addFromString($zipPath, $content);
            }
        }

        $zip->close();

        $zipFilename = $employee->personnel_code.'.zip';

        return response()->streamDownload(function () use ($tempFile) {
            readfile($tempFile);
            @unlink($tempFile);
        }, $zipFilename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function zipStore(ZipStoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $file = $request->file('file');
        $disk = config('documents.storage_disk');
        $userId = $request->user()?->id;

        $tempDir = sys_get_temp_dir().'/zip_extract_'.uniqid('', true);
        mkdir($tempDir, 0700, true);

        try {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->removeDirectory($tempDir);
                abort(422, 'Could not open zip file');
            }

            $zip->extractTo($tempDir);
            $zip->close();
        } catch (\Throwable $e) {
            $this->removeDirectory($tempDir);
            throw $e;
        }

        $uploaded = [];
        $failed = [];
        $skipped = [];

        $directories = glob($tempDir.'/*', GLOB_ONLYDIR);

        if (empty($directories)) {
            $files = glob($tempDir.'/*');
            foreach ($files as $filePath) {
                if (! is_file($filePath) || ! $this->isPathSafe($filePath, $tempDir)) {
                    continue;
                }
                $this->processZipFile($filePath, null, $employee, $disk, $userId, $uploaded, $failed, $skipped);
            }
        } else {
            foreach ($directories as $dirPath) {
                if (! $this->isPathSafe($dirPath, $tempDir)) {
                    continue;
                }
                $categoryName = basename($dirPath);
                $category = DocumentCategory::where('slug', $categoryName)
                    ->orWhere('name', $categoryName)
                    ->first();

                $categoryFiles = glob($dirPath.'/*');
                foreach ($categoryFiles as $filePath) {
                    if (! is_file($filePath) || ! $this->isPathSafe($filePath, $tempDir)) {
                        continue;
                    }
                    $this->processZipFile($filePath, $category?->id, $employee, $disk, $userId, $uploaded, $failed, $skipped);
                }
            }
        }

        $this->removeDirectory($tempDir);

        return response()->json([
            'data' => [
                'uploaded' => $uploaded,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
        ]);
    }

    private function isPathSafe(string $path, string $baseDir): bool
    {
        $real = realpath($path);
        $realBase = realpath($baseDir);

        return $real !== false && $realBase !== false && str_starts_with($real, $realBase.DIRECTORY_SEPARATOR);
    }

    private function processZipFile(
        string $filePath,
        ?int $categoryId,
        Employee $employee,
        string $disk,
        ?int $userId,
        array &$uploaded,
        array &$failed,
        array &$skipped,
    ): void {
        $originalName = basename($filePath);

        if ($categoryId === null) {
            $failed[] = ['name' => $originalName, 'error' => 'Category not found'];

            return;
        }

        $category = DocumentCategory::find($categoryId);
        if ($category && $category->documentable_type !== $employee->getMorphClass()) {
            $failed[] = ['name' => $originalName, 'error' => 'Category not applicable to employee'];

            return;
        }

        $exists = $employee->documents()
            ->where('document_category_id', $categoryId)
            ->where('original_name', $originalName)
            ->exists();

        if ($exists) {
            $skipped[] = ['name' => $originalName, 'reason' => 'duplicate'];

            return;
        }

        $categorySlug = $category?->slug ?? 'uncategorized';
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $size = filesize($filePath);

        $path = Storage::disk($disk)->putFile(
            $employee->personnel_code.'/documents/'.$categorySlug,
            $filePath,
        );

        $document = $employee->documents()->create([
            'document_category_id' => $categoryId,
            'original_name' => $originalName,
            'stored_path' => $path,
            'mime_type' => $mime,
            'file_size' => $size,
            'uploaded_by' => $userId,
        ]);

        GenerateDocumentThumbnail::dispatch($document);

        $document->load(['category', 'uploader']);

        $uploaded[] = (new DocumentResource($document))->resolve();
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($path);
    }

    public function download(Document $employeeDocument): StreamedResponse
    {
        $employeeDocument->load(['documentable', 'category']);

        if (! Storage::disk(config('documents.storage_disk'))->exists($employeeDocument->stored_path)) {
            abort(404);
        }

        $extension = pathinfo($employeeDocument->original_name, PATHINFO_EXTENSION);
        $filename = $employeeDocument->documentable->getDocumentIdentifier()
            .'-'.$employeeDocument->category?->slug
            .($extension ? '.'.$extension : '');

        return Storage::disk(config('documents.storage_disk'))->download($employeeDocument->stored_path, $filename);
    }

    public function serve(Document $employeeDocument, Request $request): StreamedResponse
    {
        $useThumbnail = $request->boolean('thumbnail') && $employeeDocument->thumbnail_path;
        $path = $useThumbnail ? $employeeDocument->thumbnail_path : $employeeDocument->stored_path;

        if (! Storage::disk(config('documents.storage_disk'))->exists($path)) {
            abort(404);
        }

        return Storage::disk(config('documents.storage_disk'))->response($path);
    }

    public function destroy(Document $employeeDocument): JsonResponse
    {
        $employeeDocument->delete();

        return response()->json(['message' => __('document.document_deleted')]);
    }

    public function trashed(Employee $employee): AnonymousResourceCollection
    {
        $documents = $employee->documents()
            ->onlyTrashed()
            ->with(['category', 'uploader', 'documentable'])
            ->latest('deleted_at')
            ->get();

        return DocumentResource::collection($documents);
    }

    public function restore(Document $employeeDocument): DocumentResource
    {
        $employeeDocument->restore();

        $employeeDocument->load(['category', 'uploader']);

        return new DocumentResource($employeeDocument);
    }

    public function forceDestroy(Document $employeeDocument): JsonResponse
    {
        Storage::disk(config('documents.storage_disk'))->delete(
            array_filter([$employeeDocument->stored_path, $employeeDocument->thumbnail_path]),
        );

        $employeeDocument->forceDelete();

        return response()->json(['message' => __('document.document_force_deleted')]);
    }
}
