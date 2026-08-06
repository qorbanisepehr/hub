<?php

namespace App\Domains\Settings\Enums;

enum BrandingImageType: string
{
    case Logo = 'logo';
    case Logotype = 'logotype';
    case Favicon = 'favicon';
    case OgImage = 'og_image';

    /**
     * Base filename (without extension) used on the branding disk.
     */
    public function filename(): string
    {
        return (string) config("settings.branding.{$this->value}.filename", $this->value);
    }

    /**
     * Per-kind config value (e.g. allowed upload types or max size).
     */
    public function config(string $key, mixed $default): mixed
    {
        return config("settings.branding.{$this->value}.{$key}", $default);
    }

    /**
     * Name of the public immutable route serving this image.
     */
    public function routeName(): string
    {
        return "settings.branding.{$this->value}";
    }
}
