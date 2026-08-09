<?php

namespace App\Domains\FormOptions\Resources;

use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormOption */
class FormOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'value' => $this->value,
            'label' => $this->label,
            'parent_value' => $this->parent_value,
            'group_label' => $this->group_label,
            'meta' => $this->meta,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
