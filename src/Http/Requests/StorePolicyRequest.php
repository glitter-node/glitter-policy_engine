<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\PolicyEngine\Rules\BoundedRuleDefinition;

final class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_extension' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*(?:-[a-z][a-z0-9_]*)+$/'],
            'policy_key' => [
                'required', 'string', 'max:150', 'regex:/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/',
                Rule::unique('glitter_policy_policies', 'policy_key')->where(
                    fn ($query) => $query->where('owner_extension', $this->input('owner_extension'))
                ),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'definition' => ['nullable', 'array', new BoundedRuleDefinition],
        ];
    }
}
