<?php

namespace Modules\Glitter\PolicyEngine\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class BoundedRuleDefinition implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || array_is_list($value)) {
            $fail('glitter-policy_engine::validation.definition.array')->translate();
            return;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $nodes = 0;
        if ($json === false
            || strlen($json) > RuleDefinitionValidator::MAX_JSON_BYTES
            || ! $this->withinLimits($value, 0, $nodes)) {
            $fail('glitter-policy_engine::validation.definition.bounds')->translate();
        }
    }

    private function withinLimits(mixed $value, int $depth, int &$nodes): bool
    {
        if ($depth > RuleDefinitionValidator::MAX_DEPTH || ++$nodes > RuleDefinitionValidator::MAX_CONDITIONS * 4) {
            return false;
        }
        if (is_string($value) && strlen($value) > RuleDefinitionValidator::MAX_STRING_LENGTH) {
            return false;
        }
        if (! is_array($value)) {
            return true;
        }
        if (count($value) > RuleDefinitionValidator::MAX_CONDITIONS) {
            return false;
        }
        foreach ($value as $item) {
            if (! $this->withinLimits($item, $depth + 1, $nodes)) {
                return false;
            }
        }

        return true;
    }
}
