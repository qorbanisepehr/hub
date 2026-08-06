<?php

namespace App\Domains\Settings\Resources;

use App\Domains\Settings\Support\BrandingSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BrandingSettings */
class BrandingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
