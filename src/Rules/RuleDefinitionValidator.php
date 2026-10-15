<?php

namespace Modules\Glitter\PolicyEngine\Rules;

use Modules\Glitter\PolicyEngine\Data\RuleValidationResult;

final class RuleDefinitionValidator
{
    public const MAX_JSON_BYTES = 65536;
    public const MAX_RULES = 100;
    public const MAX_FIELDS = 100;
    public const MAX_CONDITIONS = 500;
    public const MAX_DEPTH = 10;
    public const MAX_STRING_LENGTH = 1000;
    public const MAX_LIST_ITEMS = 100;

    private const FIELD_TYPES = ['string', 'integer', 'float', 'boolean', 'array'];
    private const OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'exists'];
    private const NUMERIC_OPERATORS = ['gt', 'gte', 'lt', 'lte'];

    /** @var array<int, array{path: string, code: string}> */
    private array $errors = [];

    private int $conditionCount = 0;

    private int $maxDepthSeen = 0;

    public function validate(array $definition): RuleValidationResult
    {
        $this->errors = [];
        $this->conditionCount = 0;
        $this->maxDepthSeen = 0;

        $encoded = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > self::MAX_JSON_BYTES) {
            $this->error('$', 'definition_too_large');
        }

        if (($definition['schema_version'] ?? null) !== '1.0') {
            $this->error('schema_version', 'unsupported_schema_version');
        }
        if (array_diff(array_keys($definition), ['schema_version', 'fields', 'rules']) !== []) {
            $this->error('$', 'unknown_definition_property');
        }

        $fields = $definition['fields'] ?? null;
        if (! is_array($fields) || array_is_list($fields)) {
            $this->error('fields', 'fields_must_be_object');
            $fields = [];
        } elseif (count($fields) > self::MAX_FIELDS) {
            $this->error('fields', 'too_many_fields');
        }

        foreach ($fields as $path => $type) {
            if (! is_string($path) || ! $this->validFieldPath($path)) {
                $this->error('fields', 'invalid_field_path');
            }
            if (! is_string($type) || ! in_array($type, self::FIELD_TYPES, true)) {
                $this->error("fields.{$path}", 'invalid_field_type');
            }
        }
        if (isset($fields['action']) && $fields['action'] !== 'string') {
            $this->error('fields.action', 'action_must_be_string');
        }

        $rules = $definition['rules'] ?? null;
        if (! is_array($rules) || ! array_is_list($rules)) {
            $this->error('rules', 'rules_must_be_array');
            $rules = [];
        } elseif (count($rules) > self::MAX_RULES) {
            $this->error('rules', 'too_many_rules');
        }

        $ruleIds = [];
        foreach ($rules as $index => $rule) {
            $path = "rules.{$index}";
            if (! is_array($rule) || array_is_list($rule)) {
                $this->error($path, 'rule_must_be_object');
                continue;
            }
            if (array_diff(array_keys($rule), ['id', 'effect', 'when']) !== []) {
                $this->error($path, 'unknown_rule_property');
            }

            $id = $rule['id'] ?? null;
            if (! is_string($id) || preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $id) !== 1) {
                $this->error("{$path}.id", 'invalid_rule_id');
            } elseif (isset($ruleIds[$id])) {
                $this->error("{$path}.id", 'duplicate_rule_id');
            } else {
                $ruleIds[$id] = true;
            }

            if (! in_array($rule['effect'] ?? null, ['allow', 'deny'], true)) {
                $this->error("{$path}.effect", 'invalid_effect');
            }

            $this->validateCondition($rule['when'] ?? null, "{$path}.when", $fields, 1);
        }

        if ($this->conditionCount > self::MAX_CONDITIONS) {
            $this->error('rules', 'too_many_conditions');
        }

        return new RuleValidationResult(
            valid: $this->errors === [],
            errors: $this->errors,
            ruleCount: count($rules),
            conditionCount: $this->conditionCount,
            maxDepth: $this->maxDepthSeen,
        );
    }

    /** @param array<string, mixed> $fields */
    private function validateCondition(mixed $condition, string $path, array $fields, int $depth): void
    {
        $this->maxDepthSeen = max($this->maxDepthSeen, $depth);
        if ($depth > self::MAX_DEPTH) {
            $this->error($path, 'maximum_depth_exceeded');
            return;
        }
        if (! is_array($condition) || array_is_list($condition)) {
            $this->error($path, 'condition_must_be_object');
            return;
        }

        $logicalKeys = array_values(array_intersect(['all', 'any'], array_keys($condition)));
        if ($logicalKeys !== []) {
            if (count($logicalKeys) !== 1 || count($condition) !== 1) {
                $this->error($path, 'logical_condition_shape_invalid');
                return;
            }
            $key = $logicalKeys[0];
            $children = $condition[$key];
            if (! is_array($children) || ! array_is_list($children) || $children === []) {
                $this->error("{$path}.{$key}", 'logical_children_required');
                return;
            }
            foreach ($children as $index => $child) {
                $this->validateCondition($child, "{$path}.{$key}.{$index}", $fields, $depth + 1);
            }
            return;
        }

        $this->conditionCount++;
        if (array_diff(array_keys($condition), ['field', 'operator', 'value']) !== []) {
            $this->error($path, 'unknown_condition_property');
        }
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        if (! is_string($field) || ! array_key_exists($field, $fields)) {
            $this->error("{$path}.field", 'field_not_registered');
            return;
        }
        if (! is_string($operator) || ! in_array($operator, self::OPERATORS, true)) {
            $this->error("{$path}.operator", 'unsupported_operator');
            return;
        }

        $fieldType = $fields[$field];
        if (in_array($operator, self::NUMERIC_OPERATORS, true) && ! in_array($fieldType, ['integer', 'float'], true)) {
            $this->error("{$path}.operator", 'numeric_operator_requires_numeric_field');
        }

        if ($operator === 'exists') {
            if (! array_key_exists('value', $condition) || ! is_bool($condition['value'])) {
                $this->error("{$path}.value", 'exists_requires_boolean');
            }
            return;
        }

        if (! array_key_exists('value', $condition)) {
            $this->error("{$path}.value", 'comparison_value_required');
            return;
        }

        $value = $condition['value'];
        if (in_array($operator, ['in', 'not_in'], true)) {
            if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_LIST_ITEMS) {
                $this->error("{$path}.value", 'membership_value_must_be_bounded_array');
                return;
            }
            foreach ($value as $index => $item) {
                if (! $this->valueMatchesType($item, $fieldType)) {
                    $this->error("{$path}.value.{$index}", 'value_type_mismatch');
                }
            }
            return;
        }

        if (! $this->valueMatchesType($value, $fieldType)) {
            $this->error("{$path}.value", 'value_type_mismatch');
        }
    }

    private function validFieldPath(string $path): bool
    {
        if ($path === 'action') {
            return true;
        }

        return preg_match('/^(subject|resource|context)\.[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', $path) === 1;
    }

    private function valueMatchesType(mixed $value, mixed $type): bool
    {
        return match ($type) {
            'string' => is_string($value) && strlen($value) <= self::MAX_STRING_LENGTH,
            'integer' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value) && count($value) <= self::MAX_LIST_ITEMS,
            default => false,
        };
    }

    private function error(string $path, string $code): void
    {
        $this->errors[] = ['path' => $path, 'code' => $code];
    }
}
