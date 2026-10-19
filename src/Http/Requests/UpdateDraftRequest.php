<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Glitter\PolicyEngine\Rules\BoundedRuleDefinition;
use Modules\Glitter\PolicyEngine\Rules\RawJsonDefinitionShape;

final class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'definition' => ['required', 'array', new BoundedRuleDefinition],
            'lock_version' => ['required', 'integer', 'min:0'],
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
