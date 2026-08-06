<?php

namespace App\Domains\Settings\Services;

use App\Domains\Settings\Enums\BrandingImageType;
use App\Domains\Settings\Repositories\SettingsRepositoryInterface;
use App\Domains\Settings\Support\BrandingSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsService
{
    private ?BrandingSettings $brandingCache = null;

    public function __construct(
        private readonly SettingsRepositoryInterface $repository,
    ) {}

    /**
     * Current branding, memoized for the duration of the request.
     */
    public function branding(): BrandingSettings
    {
        return $this->brandingCache ??= $this->buildBranding();
    }

    /**
     * Persist brand name / sub-name / colors over the stored settings.
     *
     * @param  array{name?: string, sub_name?: string, primary_color?: ?string, secondary_color?: ?string}  $data
     */
    public function updateBranding(array $data): BrandingSettings
    {
        $key = $this->brandingKey();
        $stored = $this->repository->get($key);

        foreach (['name', 'sub_name', 'primary_color', 'secondary_color'] as $field) {
            if (array_key_exists($field, $data)) {
                $stored[$field] = $data[$field];
            }
        }

        $this->repository->set($key, $stored);

        return $this->brandingCache = $this->buildBranding();
    }

    /**
     * Store the given image as the current logo/logotype, replacing any
     * previously uploaded file of the same kind.
     */
    public function uploadImage(BrandingImageType $type, UploadedFile $file): BrandingSettings
    {
        $disk = $this->disk();
        $filename = $type->filename();
        $extension = mb_strtolower($file->getClientOriginalExtension());

        $this->deleteImageFiles($disk, $filename);

        $disk->putFileAs('', $file, $filename.'.'.$extension);

        return $this->brandingCache = $this->buildBranding();
    }

    /**
     * Remove the uploaded logo/logotype so the generic SVG is used again.
     */
    public function deleteImage(BrandingImageType $type): BrandingSettings
    {
        $this->deleteImageFiles($this->disk(), $type->filename());

        return $this->brandingCache = $this->buildBranding();
    }

    /**
     * Serve the logo/logotype with immutable caching. Callers version the URL
     * with the file mtime, so a re-upload produces a fresh URL.
     */
    public function serveImage(BrandingImageType $type): StreamedResponse
    {
        $file = $this->findImageFile($type);

        if ($file === null) {
            abort(404);
        }

        return $this->disk()->response($file, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Clear the memoized branding so the next call reads from the source of truth.
     */
    public function flush(): void
    {
        $this->brandingCache = null;
        $this->repository->flush();
    }

    private function buildBranding(): BrandingSettings
    {
        $stored = $this->repository->get($this->brandingKey());

        $name = (string) ($stored['name'] ?? config('company.name', ''));
        $subName = (string) ($stored['sub_name'] ?? config('company.sub_name', ''));

        $primaryColor = (string) ($stored['primary_color'] ?? config('company.brand_color', '#db7868'));
        $secondaryColor = (string) ($stored['secondary_color'] ?? config('company.brand_secondary_color', '#1c2538'));

        $logoFile = $this->findImageFile(BrandingImageType::Logo);
        $logotypeFile = $this->findImageFile(BrandingImageType::Logotype);
        $faviconFile = $this->findImageFile(BrandingImageType::Favicon);
        $ogImageFile = $this->findImageFile(BrandingImageType::OgImage);

        $version = max(
            $this->repository->lastModified($this->brandingKey()),
            $this->imageModifiedAt($logoFile),
            $this->imageModifiedAt($logotypeFile),
            $this->imageModifiedAt($faviconFile),
            $this->imageModifiedAt($ogImageFile),
        );

        return new BrandingSettings(
            name: $name,
            subName: $subName,
            logoUrl: $this->imageUrl(BrandingImageType::Logo, $logoFile),
            logotypeUrl: $this->imageUrl(BrandingImageType::Logotype, $logotypeFile),
            faviconUrl: $this->imageUrl(BrandingImageType::Favicon, $faviconFile),
            ogImageUrl: $this->imageUrl(BrandingImageType::OgImage, $ogImageFile),
            logoSvg: $this->svgContent($logoFile),
            logotypeSvg: $this->svgContent($logotypeFile),
            primaryColor: $primaryColor,
            secondaryColor: $secondaryColor,
            version: $version,
        );
    }

    private function brandingKey(): string
    {
        return (string) config('settings.branding.key', 'branding');
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('settings.branding.disk', 'branding'));
    }

    /**
     * Path of the currently uploaded image for the given type, or null when
     * none exists (the generic SVG default applies).
     */
    private function findImageFile(BrandingImageType $type): ?string
    {
        $filename = $type->filename();

        foreach ($this->disk()->files() as $path) {
            if (pathinfo($path, PATHINFO_FILENAME) === $filename) {
                return $path;
            }
        }

        return null;
    }

    private function imageModifiedAt(?string $path): int
    {
        return $path === null ? 0 : (int) $this->disk()->lastModified($path);
    }

    /**
     * Raw SVG markup of the given uploaded image when it is an SVG file, or
     * null for raster uploads / no upload. The SPA recolors this to the live
     * brand color inline, avoiding a client-side fetch (and its load flash).
     */
    private function svgContent(?string $path): ?string
    {
        if ($path === null || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
            return null;
        }

        return $this->disk()->get($path);
    }

    private function imageUrl(BrandingImageType $type, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return route($type->routeName(), ['v' => $this->imageModifiedAt($path)]);
    }

    /**
     * @param  Filesystem  $disk
     */
    private function deleteImageFiles($disk, string $filename): void
    {
        foreach ($disk->files() as $path) {
            if (pathinfo($path, PATHINFO_FILENAME) === $filename) {
                $disk->delete($path);
            }
        }
    }
}
