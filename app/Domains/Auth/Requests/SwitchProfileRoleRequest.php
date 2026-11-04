<?php

namespace App\Domains\Auth\Requests;

use App\Domains\Authorization\Requests\Concerns\ValidatesActiveRoleSwitch;
use Illuminate\Foundation\Http\FormRequest;

class SwitchProfileRoleRequest extends FormRequest
{
    use ValidatesActiveRoleSwitch;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The profile switch is always about the signed-in user.
        return [
            'role_id' => $this->activeRoleRule($this->user()->id),
        ];
    }
}
