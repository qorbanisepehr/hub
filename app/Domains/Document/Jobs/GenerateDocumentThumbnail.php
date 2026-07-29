<?php

namespace App\Domains\Document\Jobs;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
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

    public function handle(DocumentRepositoryInterface $repository): void
    {
        $mimeType = $this->document->mime_type;

        if (! str_starts_with($mimeType, 'image/')) {
            return;
        }

        $disk = Storage::disk($this->document->disk);
        $sourcePath = $disk->path($this->document->path);

        if (! file_exists($sourcePath)) {
            Log::warning('Thumbnail generation skipped: source file not found', [
                'document_id' => $this->document->id,
                'path' => $this->document->path,
            ]);

            return;
        }

        $thumbRelPath = $repository->getThumbnailPath($this->document->path);
        $thumbAbsPath = $disk->path($thumbRelPath);

        $thumbDir = dirname($thumbAbsPath);
        if (! is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $maxWidth = config('documents.thumbnail.max_width', 300);
        $maxHeight = config('documents.thumbnail.max_height', 300);
        $quality = config('documents.thumbnail.quality', 80);

        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            Log::warning('Thumbnail generation skipped: unable to read image dimensions', [
                'document_id' => $this->document->id,
                'path' => $sourcePath,
            ]);

            return;
        }

        [$origWidth, $origHeight, $type] = $imageInfo;

        $scale = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
        $newWidth = (int) round($origWidth * $scale);
        $newHeight = (int) round($origHeight * $scale);

        $srcImage = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => null,
        };

        if ($srcImage === null) {
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

            Log::info('Thumbnail generated', [
                'document_id' => $this->document->id,
                'thumb_path' => $thumbRelPath,
            ]);
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
