<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EvaluationListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_extension' => ['nullable', 'string', 'max:100'],
            'policy_key' => ['nullable', 'string', 'max:150'],
            'policy_id' => ['nullable', 'integer', 'min:1'],
            'decision' => ['nullable', 'string', 'in:allow,deny,indeterminate'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
