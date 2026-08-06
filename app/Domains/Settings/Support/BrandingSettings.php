<?php

namespace App\Domains\Settings\Support;

class BrandingSettings
{
    /**
     * @param  string  $name  Brand name shown to visitors.
     * @param  string  $subName  Short tagline/sub-name.
     * @param  string|null  $logoUrl  Public URL of the uploaded logo (immutable, versioned) or null when using the default SVG.
     * @param  string|null  $logotypeUrl  Public URL of the uploaded logotype or null when using the default SVG.
     * @param  string|null  $faviconUrl  Public URL of the uploaded favicon or null when using the bundled default.
     * @param  string|null  $ogImageUrl  Public URL of the uploaded social-share image or null when unset.
     * @param  string|null  $logoSvg  Inline SVG markup of the uploaded logo when it is an SVG, so the SPA recolors it synchronously without a fetch.
     * @param  string|null  $logotypeSvg  Inline SVG markup of the uploaded logotype when it is an SVG.
     * @param  string  $primaryColor  Brand color (hex), drives the fallback logo and theme accent.
     * @param  string  $secondaryColor  Secondary brand color (hex).
     * @param  int  $version  Latest modification timestamp across branding data/files; changes whenever branding changes.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $subName,
        public readonly ?string $logoUrl,
        public readonly ?string $logotypeUrl,
        public readonly ?string $faviconUrl,
        public readonly ?string $ogImageUrl,
        public readonly ?string $logoSvg,
        public readonly ?string $logotypeSvg,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly int $version,
    ) {}

    /**
     * @return array{name: string, sub_name: string, logo_url: string|null, logotype_url: string|null, favicon_url: string|null, og_image_url: string|null, logo_svg: string|null, logotype_svg: string|null, primary_color: string, secondary_color: string, version: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sub_name' => $this->subName,
            'logo_url' => $this->logoUrl,
            'logotype_url' => $this->logotypeUrl,
            'favicon_url' => $this->faviconUrl,
            'og_image_url' => $this->ogImageUrl,
            'logo_svg' => $this->logoSvg,
            'logotype_svg' => $this->logotypeSvg,
            'primary_color' => $this->primaryColor,
            'secondary_color' => $this->secondaryColor,
            'version' => $this->version,
        ];
    }
}
