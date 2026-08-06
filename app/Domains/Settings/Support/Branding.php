<?php

namespace App\Domains\Settings\Support;

use App\Domains\Settings\Services\SettingsService;

/**
 * Read-only facade over the current branding, used by Blade templates so
 * server-rendered spots (title, preload) always reflect stored settings.
 */
class Branding
{
    public static function settings(): BrandingSettings
    {
        return app(SettingsService::class)->branding();
    }

    public static function name(): string
    {
        return self::settings()->name;
    }

    public static function subName(): string
    {
        return self::settings()->subName;
    }

    public static function logoUrl(): ?string
    {
        return self::settings()->logoUrl;
    }

    public static function logotypeUrl(): ?string
    {
        return self::settings()->logotypeUrl;
    }

    public static function faviconUrl(): ?string
    {
        return self::settings()->faviconUrl;
    }

    public static function ogImageUrl(): ?string
    {
        return self::settings()->ogImageUrl;
    }

    public static function primaryColor(): string
    {
        return self::settings()->primaryColor;
    }

    public static function secondaryColor(): string
    {
        return self::settings()->secondaryColor;
    }

    /**
     * Full branding payload injected into the SPA as window.__BRANDING__.
     *
     * @return array{name: string, sub_name: string, logo_url: string|null, logotype_url: string|null, favicon_url: string|null, og_image_url: string|null, logo_svg: string|null, logotype_svg: string|null, primary_color: string, secondary_color: string, version: int}
     */
    public static function data(): array
    {
        return self::settings()->toArray();
    }
}
