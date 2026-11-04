<?php

namespace App\Domains\Authorization\Requests;

use App\Domains\Authorization\Requests\Concerns\ValidatesActiveRoleSwitch;
use Illuminate\Foundation\Http\FormRequest;

class SwitchActiveRoleRequest extends FormRequest
{
    use ValidatesActiveRoleSwitch;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The admin switch validates the ROUTE user's roles — the caller may
        // switch somebody else's active role, so the id source is the route
        // model, not the authenticated user.
        return [
            'role_id' => $this->activeRoleRule($this->route('user')->id),
        ];
    }
}
