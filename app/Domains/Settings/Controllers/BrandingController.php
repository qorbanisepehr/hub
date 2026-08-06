<?php

namespace App\Domains\Settings\Controllers;

use App\Domains\Settings\Enums\BrandingImageType;
use App\Domains\Settings\Requests\UpdateBrandingRequest;
use App\Domains\Settings\Requests\UploadBrandingImageRequest;
use App\Domains\Settings\Resources\BrandingResource;
use App\Domains\Settings\Services\SettingsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingController
{
    public function __construct(
        private readonly SettingsService $service,
    ) {}

    public function update(UpdateBrandingRequest $request): BrandingResource
    {
        return new BrandingResource($this->service->updateBranding($request->validated()));
    }

    public function uploadLogo(UploadBrandingImageRequest $request): BrandingResource
    {
        return new BrandingResource(
            $this->service->uploadImage(BrandingImageType::Logo, $request->file('file')),
        );
    }

    public function deleteLogo(): BrandingResource
    {
        return new BrandingResource($this->service->deleteImage(BrandingImageType::Logo));
    }

    public function uploadLogotype(UploadBrandingImageRequest $request): BrandingResource
    {
        return new BrandingResource(
            $this->service->uploadImage(BrandingImageType::Logotype, $request->file('file')),
        );
    }

    public function deleteLogotype(): BrandingResource
    {
        return new BrandingResource($this->service->deleteImage(BrandingImageType::Logotype));
    }

    public function uploadFavicon(UploadBrandingImageRequest $request): BrandingResource
    {
        return new BrandingResource(
            $this->service->uploadImage(BrandingImageType::Favicon, $request->file('file')),
        );
    }

    public function deleteFavicon(): BrandingResource
    {
        return new BrandingResource($this->service->deleteImage(BrandingImageType::Favicon));
    }

    public function uploadOgImage(UploadBrandingImageRequest $request): BrandingResource
    {
        return new BrandingResource(
            $this->service->uploadImage(BrandingImageType::OgImage, $request->file('file')),
        );
    }

    public function deleteOgImage(): BrandingResource
    {
        return new BrandingResource($this->service->deleteImage(BrandingImageType::OgImage));
    }

    public function logo(): StreamedResponse
    {
        return $this->service->serveImage(BrandingImageType::Logo);
    }

    public function logotype(): StreamedResponse
    {
        return $this->service->serveImage(BrandingImageType::Logotype);
    }

    public function favicon(): StreamedResponse
    {
        return $this->service->serveImage(BrandingImageType::Favicon);
    }

    public function ogImage(): StreamedResponse
    {
        return $this->service->serveImage(BrandingImageType::OgImage);
    }
}
