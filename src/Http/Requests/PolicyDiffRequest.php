<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class PolicyDiffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'in:published,revision,draft'],
            'from_version' => ['sometimes', 'required_if:from,revision', 'integer', 'min:1', 'prohibited_unless:from,revision'],
            'to' => ['required', 'string', 'in:published,revision,draft'],
            'to_version' => ['sometimes', 'required_if:to,revision', 'integer', 'min:1', 'prohibited_unless:to,revision'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        $data = $this->query->all();
        if (! array_key_exists('from', $data) && ! array_key_exists('to', $data)) {
            $data['from'] = 'published';
            $data['to'] = 'draft';
        }

        return $data;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $query = $this->query->all();
            $unknownKeys = array_diff(array_keys($query), ['from', 'from_version', 'to', 'to_version']);
            if ($unknownKeys !== []) {
                $validator->errors()->add('query', __('glitter-policy_engine::messages.diff.invalid_query_parameters'));
            }

            $fromType = $query['from'] ?? (! array_key_exists('from', $query) && ! array_key_exists('to', $query) ? 'published' : null);
            $toType = $query['to'] ?? (! array_key_exists('from', $query) && ! array_key_exists('to', $query) ? 'draft' : null);
            foreach ([['from', $fromType], ['to', $toType]] as [$selector, $type]) {
                $versionKey = $selector.'_version';
                $hasVersion = array_key_exists($versionKey, $query);
                if ($hasVersion !== ($type === 'revision')) {
                    $validator->errors()->add($versionKey, __('glitter-policy_engine::messages.diff.version_selector_mismatch'));
                }
            }
        });
    }
}
