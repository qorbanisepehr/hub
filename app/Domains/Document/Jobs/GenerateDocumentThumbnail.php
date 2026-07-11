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

    private const int THUMB_MAX_WIDTH = 400;

    private const int THUMB_QUALITY = 80;

    public function __construct(
        public Document $document,
    ) {}

    public function handle(): void
    {
        $mimeType = $this->document->mime_type;

        if (str_starts_with($mimeType, 'image/')) {
            $this->generateImageThumbnail();
        }
    }

    private function generateImageThumbnail(): void
    {
        $disk = Storage::disk(config('documents.storage_disk'));
        $sourcePath = $disk->path($this->document->stored_path);

        if (! file_exists($sourcePath)) {
            Log::warning('Thumbnail generation skipped: source file not found', [
                'document_id' => $this->document->id,
                'stored_path' => $this->document->stored_path,
            ]);

            return;
        }

        $docDir = dirname($this->document->stored_path);
        $storedBasename = pathinfo($this->document->stored_path, PATHINFO_FILENAME);
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

        if ($origWidth <= self::THUMB_MAX_WIDTH) {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        } else {
            $newWidth = self::THUMB_MAX_WIDTH;
            $newHeight = (int) round($origHeight * (self::THUMB_MAX_WIDTH / $origWidth));
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

            imagewebp($thumbImage, $thumbAbsPath, self::THUMB_QUALITY);

            $this->document->updateQuietly(['thumbnail_path' => $thumbRelPath]);
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
