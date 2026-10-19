<?php

namespace Modules\Glitter\PolicyEngine\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidRuleDefinition implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('glitter-policy_engine::validation.definition.array')->translate();
            return;
        }

        $result = (new RuleDefinitionValidator)->validate($value);
        if (! $result->valid) {
            $fail('glitter-policy_engine::validation.definition.invalid')->translate();
        }
    }
}
