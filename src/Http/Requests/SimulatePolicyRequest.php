<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Glitter\PolicyEngine\Support\JsonObjectInputShape;

final class SimulatePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jsonRequest = $this->isJson();

        return [
            'owner_extension' => ['required', 'string', 'max:100'],
            'policy_key' => ['required', 'string', 'max:150'],
            'subject' => [$jsonRequest ? 'present' : 'required', 'array', 'max:100'],
            'resource' => [$jsonRequest ? 'present' : 'required', 'array', 'max:100'],
            'action' => ['required', 'string', 'max:150'],
            'context' => ['nullable', 'array', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->isJson()) {
                foreach (JsonObjectInputShape::invalidArrayFields(
                    $this->all(),
                    ['subject', 'resource'],
                    ['context'],
                ) as $field) {
                    if (! $validator->errors()->has($field)) {
                        $validator->errors()->add(
                            $field,
                            __('glitter-policy_engine::validation.evaluation.object', ['attribute' => $field]),
                        );
                    }
                }
                return;
            }

            $rawBody = $this->getContent();
            if (! JsonObjectInputShape::isValidJson($rawBody)) {
                foreach (['subject', 'resource'] as $field) {
                    $validator->errors()->forget($field);
                    $validator->errors()->add(
                        $field,
                        __('glitter-policy_engine::validation.evaluation.invalid_json'),
                    );
                }
                return;
            }

            foreach (JsonObjectInputShape::invalidFields(
                $rawBody,
                ['subject', 'resource'],
                ['context'],
            ) as $field) {
                if (! $validator->errors()->has($field)) {
                    $validator->errors()->add(
                        $field,
                        __('glitter-policy_engine::validation.evaluation.object', ['attribute' => $field]),
                    );
                }
            }
        });
    }
}
