<?php

namespace Modules\Glitter\PolicyEngine\Support;

use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;

final class PolicyDefinitionSafetyInspector
{
    /** @return array{status: string, reason_codes: array<int, string>, condition_count: ?int, ambiguous_condition_count: ?int, unverified_array_comparison_count: ?int} */
    public static function inspect(mixed $definition, RuleDefinitionValidator $validator, bool $arrayComparisonShapeVerified = false): array
    {
        if (! is_array($definition) || ! $validator->validate($definition)->valid) {
            return [
                'status' => 'unknown',
                'reason_codes' => [],
                'condition_count' => null,
                'ambiguous_condition_count' => null,
                'unverified_array_comparison_count' => null,
            ];
        }

        $conditionCount = 0;
        $ambiguousConditionCount = 0;
        $unverifiedArrayComparisonCount = 0;
        foreach ($definition['rules'] as $rule) {
            [$membershipCount, $ambiguousCount, $unverifiedCount] = self::countUnsafeConditions(
                $rule['when'],
                $definition['fields'],
                $arrayComparisonShapeVerified,
            );
            $conditionCount += $membershipCount;
            $ambiguousConditionCount += $ambiguousCount;
            $unverifiedArrayComparisonCount += $unverifiedCount;
        }

        if ($conditionCount === 0 && $ambiguousConditionCount === 0 && $unverifiedArrayComparisonCount === 0) {
            return [
                'status' => 'safe',
                'reason_codes' => [],
                'condition_count' => 0,
                'ambiguous_condition_count' => 0,
                'unverified_array_comparison_count' => 0,
            ];
        }

        $reasonCodes = [];
        if ($conditionCount > 0) {
            $reasonCodes[] = 'unsafe_empty_membership_condition';
        }
        if ($ambiguousConditionCount > 0) {
            $reasonCodes[] = 'ambiguous_empty_array_comparison';
        }
        if ($unverifiedArrayComparisonCount > 0) {
            $reasonCodes[] = 'unverified_array_comparison_shape';
        }

        return [
            'status' => 'unsafe',
            'reason_codes' => $reasonCodes,
            'condition_count' => $conditionCount,
            'ambiguous_condition_count' => $ambiguousConditionCount,
            'unverified_array_comparison_count' => $unverifiedArrayComparisonCount,
        ];
    }

    /** @param array<string, mixed> $condition @param array<string, string> $fields @return array{int, int, int} */
    private static function countUnsafeConditions(array $condition, array $fields, bool $arrayComparisonShapeVerified): array
    {
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;
        $field = $condition['field'] ?? null;
        $membershipCount = in_array($operator, ['in', 'not_in'], true) && $value === [] ? 1 : 0;
        $ambiguousCount = 0;
        $unverifiedCount = 0;

        if (is_string($field) && ($fields[$field] ?? null) === 'array') {
            if (! $arrayComparisonShapeVerified && in_array($operator, ['eq', 'neq', 'in', 'not_in'], true)) {
                $unverifiedCount++;
            }
            if (in_array($operator, ['eq', 'neq'], true) && self::containsEmptyArray($value)) {
                $ambiguousCount++;
            } elseif (in_array($operator, ['in', 'not_in'], true)
                && is_array($value)
                && $value !== []
                && self::containsEmptyArray($value)) {
                $ambiguousCount++;
            }
        }

        foreach (['all', 'any'] as $logicalOperator) {
            if (! array_key_exists($logicalOperator, $condition)) {
                continue;
            }

            foreach ($condition[$logicalOperator] as $child) {
                [$childMembershipCount, $childAmbiguousCount, $childUnverifiedCount] = self::countUnsafeConditions(
                    $child,
                    $fields,
                    $arrayComparisonShapeVerified,
                );
                $membershipCount += $childMembershipCount;
                $ambiguousCount += $childAmbiguousCount;
                $unverifiedCount += $childUnverifiedCount;
            }
        }

        return [$membershipCount, $ambiguousCount, $unverifiedCount];
    }

    private static function containsEmptyArray(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }
        if ($value === []) {
            return true;
        }

        foreach ($value as $item) {
            if (self::containsEmptyArray($item)) {
                return true;
            }
        }

        return false;
    }
}
