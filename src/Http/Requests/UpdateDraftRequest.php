<?php

namespace Modules\Glitter\PolicyEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Glitter\PolicyEngine\Rules\BoundedRuleDefinition;

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
}
