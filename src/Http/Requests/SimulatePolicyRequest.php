<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SimulatePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_extension' => ['required', 'string', 'max:100'],
            'policy_key' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'array', 'max:100'],
            'resource' => ['required', 'array', 'max:100'],
            'action' => ['required', 'string', 'max:150'],
            'context' => ['nullable', 'array', 'max:100'],
        ];
    }
}
