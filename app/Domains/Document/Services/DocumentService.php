<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;
use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Recruitment\Models\Questionnaire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function upload(Documentable $owner, UploadedFile $file, array $data = []): Document
    {
        $categoryId = $data['document_category_id'];
        $category = DocumentCategory::findOrFail($categoryId);
        $categorySlug = $category->slug;
        $disk = config('documents.storage_disk');

        $prefix = $owner->getDocumentRouteType();
        $identifier = $this->getIdentifier($owner);
        $storedPath = $file->store(
            "$prefix/$identifier/documents/$categorySlug",
            $disk,
        );

        $document = $owner->documents()->create([
            'document_category_id' => $categoryId,
            'status' => Document::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'meta' => $data['meta'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? auth()->id(),
        ]);

        $revision = $document->revisions()->create([
            'stored_path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'form_data' => $data['form_data'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? auth()->id(),
        ]);

        $document->updateQuietly(['current_revision_id' => $revision->id]);

        GenerateDocumentThumbnail::dispatch($document);

        return $document->load(['category', 'currentRevision', 'uploader']);
    }

    public function delete(Document $document): void
    {
        $document->delete();
    }

    public function forceDelete(Document $document): void
    {
        $disk = config('documents.storage_disk');

        foreach ($document->revisions as $revision) {
            Storage::disk($disk)->delete(array_filter([
                $revision->stored_path,
                $revision->thumbnail_path,
            ]));
        }

        $document->forceDelete();
    }

    public function confirm(Document $document, ?string $notes = null): Document
    {
        $document->update([
            'status' => Document::STATUS_CONFIRMED,
            'notes' => $notes ?? $document->notes,
        ]);

        return $document->fresh()->load(['currentRevision', 'category']);
    }

    public function reject(Document $document, string $reason): Document
    {
        $document->update([
            'status' => Document::STATUS_REJECTED,
            'notes' => $reason,
        ]);

        return $document->fresh()->load(['currentRevision', 'category']);
    }

    private function getIdentifier(Documentable $owner): string
    {
        if ($owner instanceof Questionnaire) {
            return $owner->uuid;
        }

        return (string) $owner->getKey();
    }
}
