<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\PolicyEngine\Rules\BoundedRuleDefinition;
use Modules\Glitter\PolicyEngine\Rules\RawJsonDefinitionShape;

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

    public function withValidator($validator): void
    {
        if (! $this->isJson()) {
            return;
        }

        $validator->after(function ($validator): void {
            if ($validator->errors()->has('definition')) {
                return;
            }

            $rawBody = $this->getContent();
            if ($rawBody === '') {
                return;
            }

            if (strlen($rawBody) > RawJsonDefinitionShape::MAX_RAW_REQUEST_BYTES) {
                $validator->errors()->add(
                    'definition',
                    __('glitter-policy_engine::validation.definition.bounds'),
                );
                return;
            }

            foreach (RawJsonDefinitionShape::errors($rawBody) as $path) {
                $validator->errors()->add(
                    $path,
                    __('glitter-policy_engine::validation.definition.shape'),
                );
            }
        });
    }
}
