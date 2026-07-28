<?php

namespace App\Domains\Document\Jobs;

use App\Domains\Document\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateDocumentThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public Document $document,
    ) {}

    public function handle(): void
    {
        $this->document->load('currentRevision');

        $revision = $this->document->currentRevision;

        if ($revision === null) {
            Log::warning('Thumbnail generation skipped: no current revision', [
                'document_id' => $this->document->id,
            ]);

            return;
        }

        $mimeType = $revision->mime_type;

        if (str_starts_with($mimeType, 'image/')) {
            $this->generateImageThumbnail($revision);
        }
    }

    private function generateImageThumbnail($revision): void
    {
        $disk = Storage::disk(config('documents.storage_disk'));
        $sourcePath = $disk->path($revision->stored_path);

        if (! file_exists($sourcePath)) {
            Log::warning('Thumbnail generation skipped: source file not found', [
                'document_id' => $this->document->id,
                'stored_path' => $revision->stored_path,
            ]);

            return;
        }

        $maxWidth = config('documents.thumbnail.max_width', 300);
        $quality = config('documents.thumbnail.quality', 80);

        $docDir = dirname($revision->stored_path);
        $storedBasename = pathinfo($revision->stored_path, PATHINFO_FILENAME);
        $thumbRelPath = $docDir.'/thumbnail/'.$storedBasename.'.webp';
        $thumbAbsPath = $disk->path($thumbRelPath);

        $thumbDir = dirname($thumbAbsPath);
        if (! is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            Log::warning('Thumbnail generation skipped: unable to read image dimensions', [
                'document_id' => $this->document->id,
                'path' => $sourcePath,
            ]);

            return;
        }

        [$origWidth, $origHeight, $type] = $imageInfo;

        if ($origWidth <= $maxWidth) {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = (int) round($origHeight * ($maxWidth / $origWidth));
        }

        $srcImage = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => null,
        };

        if ($srcImage === null) {
            Log::warning('Thumbnail generation skipped: unsupported image type', [
                'document_id' => $this->document->id,
                'type' => $type,
            ]);

            return;
        }

        $thumbImage = null;

        try {
            $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
            imagefill($thumbImage, 0, 0, $transparent);

            imagecopyresampled(
                $thumbImage, $srcImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                imagesx($srcImage), imagesy($srcImage),
            );

            imagewebp($thumbImage, $thumbAbsPath, $quality);

            $revision->updateQuietly(['thumbnail_path' => $thumbRelPath]);
        } catch (\Throwable $e) {
            Log::error('Thumbnail generation failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (isset($thumbImage)) {
                unset($thumbImage);
            }
            if (isset($srcImage)) {
                unset($srcImage);
            }
        }
    }
}
