<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required_without:enabled', 'nullable', 'string', 'max:2000'],
            'enabled' => ['required_without:description', 'boolean'],
        ];
    }
}
