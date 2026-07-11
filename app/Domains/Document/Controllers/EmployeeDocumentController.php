<?php

namespace App\Domains\Document\Controllers;

use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\StoreEmployeeDocumentRequest;
use App\Domains\Document\Resources\DocumentResource;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function download(Document $employeeDocument): StreamedResponse
    {
        $employeeDocument->load(['documentable', 'category']);

        if (! Storage::disk(config('documents.storage_disk'))->exists($employeeDocument->stored_path)) {
            abort(404);
        }

        $extension = pathinfo($employeeDocument->original_name, PATHINFO_EXTENSION);
        $filename = $employeeDocument->documentable->getDocumentIdentifier()
            .'-'.$employeeDocument->category->slug
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
