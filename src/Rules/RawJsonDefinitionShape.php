<?php

namespace Modules\Glitter\PolicyEngine\Rules;

use JsonException;
use stdClass;

final class RawJsonDefinitionShape
{
    public const MAX_RAW_REQUEST_BYTES = RuleDefinitionValidator::MAX_JSON_BYTES * 8;

    /** @return array<int, string> */
    public static function errors(string $rawBody): array
    {
        if (strlen($rawBody) > self::MAX_RAW_REQUEST_BYTES) {
            return ['definition'];
        }

        try {
            $request = json_decode(
                $rawBody,
                false,
                RuleDefinitionValidator::MAX_DEPTH + 4,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return ['definition'];
        }

        if (! $request instanceof stdClass) {
            return ['definition'];
        }

        if (! property_exists($request, 'definition') || $request->definition === null) {
            return [];
        }

        $definition = $request->definition;
        if (! $definition instanceof stdClass) {
            return ['definition'];
        }

        $errors = [];
        $fields = [];
        if (property_exists($definition, 'fields')) {
            if (! $definition->fields instanceof stdClass) {
                $errors[] = 'definition.fields';
            } else {
                $fields = get_object_vars($definition->fields);
            }
        }

        if (property_exists($definition, 'rules')) {
            if (! is_array($definition->rules)) {
                $errors[] = 'definition.rules';
            } else {
                foreach ($definition->rules as $index => $rule) {
                    $path = "definition.rules.{$index}";
                    if (! $rule instanceof stdClass) {
                        $errors[] = $path;
                        continue;
                    }

                    if (property_exists($rule, 'when')) {
                        self::validateCondition($rule->when, "{$path}.when", $fields, $errors);
                    }
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string, mixed> $fields @param array<int, string> $errors */
    private static function validateCondition(mixed $condition, string $path, array $fields, array &$errors): void
    {
        if (! $condition instanceof stdClass) {
            $errors[] = $path;
            return;
        }

        foreach (['all', 'any'] as $logicalOperator) {
            if (! property_exists($condition, $logicalOperator)) {
                continue;
            }

            $children = $condition->{$logicalOperator};
            if (! is_array($children)) {
                $errors[] = "{$path}.{$logicalOperator}";
                continue;
            }

            foreach ($children as $index => $child) {
                self::validateCondition($child, "{$path}.{$logicalOperator}.{$index}", $fields, $errors);
            }
        }

        if (! property_exists($condition, 'value')) {
            return;
        }

        $operator = $condition->operator ?? null;
        $field = $condition->field ?? null;
        $requiresList = in_array($operator, ['in', 'not_in'], true)
            || (is_string($field) && ($fields[$field] ?? null) === 'array');

        if ($requiresList && ! is_array($condition->value)) {
            $errors[] = "{$path}.value";
            return;
        }

        if ($requiresList && self::containsObject($condition->value)) {
            $errors[] = "{$path}.value";
        }

        if (in_array($operator, ['in', 'not_in'], true)
            && is_array($condition->value)
            && is_string($field)
            && ($fields[$field] ?? null) === 'array') {
            foreach ($condition->value as $index => $item) {
                if (! is_array($item)) {
                    $errors[] = "{$path}.value.{$index}";
                }
            }
        }
    }

    private static function containsObject(mixed $value): bool
    {
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $item) {
                if (self::containsObject($item)) {
                    return true;
                }
            }

            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::containsObject($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
