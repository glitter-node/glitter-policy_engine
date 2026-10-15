<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PolicyListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_extension' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:draft,published,disabled'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
