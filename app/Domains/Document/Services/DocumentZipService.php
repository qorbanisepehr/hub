<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Document domain's bundle: a structured zip of already-authorized
 * document usages, one folder per owner (the same owner label that feeds
 * `structureNames()`), ASCII file names from the placement slug, duplicates
 * suffixed in order.
 *
 * An adapter, not an implementation. Every piece of real work — verifying the
 * sources, capping the set, resolving collisions, building to a temp file so
 * nothing is buffered in memory — belongs to {@see BundleBuilder} in the export
 * kernel, which is where a board meeting's bundle can reach it too. What is left
 * here is the part only this domain can answer: which files a usage means, what
 * they are called, and which folder they belong in.
 *
 * The kernel never authorizes and never skips: callers must have passed the
 * single-file `DocumentAction::Download` check for every usage in the set
 * (deny-all happens at the boundary), and a file that vanished from its disk
 * aborts the whole bundle rather than shipping an incomplete archive. Both are
 * preserved here by re-throwing the kernel's missing-source error as this
 * domain's 404.
 */
class DocumentZipService
{
    private const FOLDER_FALLBACK = 'documents';

    public function __construct(
        private readonly DocumentService $documentService,
        private readonly BundleBuilder $bundles,
    ) {}

    /**
     * Build the archive on a temporary local file and return its path and the
     * response file name. The caller is responsible for unlinking the temp
     * file after it has been streamed.
     *
     * @param  Collection<int, DocumentUsage>  $usages
     * @return array{path: string, filename: string}
     */
    public function build(Collection $usages): array
    {
        // Pre-checked here rather than left to the kernel's own check, because
        // only this domain can say WHICH document went missing and answer 404
        // on it. The kernel repeats the check as a backstop for adapters that
        // do not pre-verify, but its error cannot name a model.
        $this->assertAllFilesExist($usages);

        $bundle = $this->bundles->build(
            $usages
                ->map(fn (DocumentUsage $usage): BundleEntry => BundleEntry::fromFile(
                    path: Storage::disk($usage->document->disk)->path($usage->document->path),
                    entryName: $this->entryName($usage),
                    folder: $this->folderSlug($usage),
                ))
                ->values()
                ->all(),
            filename: 'documents-'.now()->format('Y-m-d').'.zip',
        );

        return [
            'path' => $bundle->path,
            'filename' => $bundle->filename,
        ];
    }

    /**
     * Same guard the single-file endpoints apply after authorizing: a file that
     * no longer exists is a 404, never a silent hole in the bundle.
     *
     * @param  Collection<int, DocumentUsage>  $usages
     */
    private function assertAllFilesExist(Collection $usages): void
    {
        foreach ($usages as $usage) {
            $document = $usage->document;

            if (! Storage::disk($document->disk)->exists($document->path)) {
                throw (new ModelNotFoundException)->setModel(
                    Document::class,
                    $document->getKey(),
                );
            }
        }
    }

    /**
     * ASCII file name from the placement slug (`structureNames()`), extension
     * from the stored file so the downloaded entry opens correctly.
     */
    private function entryName(DocumentUsage $usage): string
    {
        $document = $usage->document;

        $slug = $this->documentService->structureNames($document, $usage)['slug'];

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return $extension !== '' ? "{$slug}.{$extension}" : $slug;
    }

    /**
     * The owning entity's public label — the same source `structureNames()`
     * prefixes its display names with — as the bundle's per-owner folder.
     */
    private function folderSlug(DocumentUsage $usage): string
    {
        $entity = $usage->resolveEntity();

        $ownerLabel = $entity instanceof Documentable
            ? $entity->getDocumentOwnerLabel()
            : null;

        $slug = $ownerLabel !== null && trim($ownerLabel) !== ''
            ? Str::slug($ownerLabel)
            : '';

        return $slug !== '' ? $slug : self::FOLDER_FALLBACK;
    }
}
