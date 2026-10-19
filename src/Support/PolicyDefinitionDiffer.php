<?php

namespace Modules\Glitter\PolicyEngine\Support;

use InvalidArgumentException;
use JsonException;
use LengthException;
use UnexpectedValueException;

final class PolicyDefinitionDiffer
{
    public const MAX_RESULT_BYTES = 262144;

    public const RESULT_TOO_LARGE = 'policy_definition_diff_result_too_large';

    public const INPUT_INVALID = 'policy_definition_diff_input_invalid';

    public const ENCODING_FAILED = 'policy_definition_diff_encoding_failed';

    private const MAX_VALUE_DEPTH = 128;

    private const MAX_VALUE_NODES = 100000;

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    public function diff(array $before, array $after): array
    {
        $this->assertComparable($before);
        $this->assertComparable($after);

        $changes = [];
        $limitations = [];

        $this->compareRootValue('schema_version', 'schema_version_changed', $before, $after, $changes);
        $this->compareDefinitionProperties($before, $after, $changes);

        $fieldsMode = $this->compareFields($before, $after, $changes, $limitations);
        $rulesMode = $this->compareRules($before, $after, $changes, $limitations);

        $result = [
            'changed' => $changes !== [],
            'summary' => $this->summarize($changes),
            'comparison_mode' => [
                'fields' => $fieldsMode,
                'rules' => $rulesMode,
            ],
            'comparison_complete' => $limitations === [],
            'limitations' => $limitations,
            'changes' => $changes,
        ];
        $result = $this->canonicalize($result);

        try {
            $encoded = json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new UnexpectedValueException(self::ENCODING_FAILED);
        }

        if (strlen($encoded) > self::MAX_RESULT_BYTES) {
            throw new LengthException(self::RESULT_TOO_LARGE);
        }

        return $result;
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<int, array<string, mixed>> $changes */
    private function compareRootValue(string $key, string $type, array $before, array $after, array &$changes): void
    {
        $beforeHas = array_key_exists($key, $before);
        $afterHas = array_key_exists($key, $after);
        if ($beforeHas === $afterHas && (! $beforeHas || $this->valuesEqual($before[$key], $after[$key]))) {
            return;
        }

        $change = ['type' => $type, 'path' => '/'.self::escapePointerToken($key)];
        if ($beforeHas) {
            $change['before'] = $before[$key];
        }
        if ($afterHas) {
            $change['after'] = $after[$key];
        }
        $changes[] = $change;
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<int, array<string, mixed>> $changes */
    private function compareDefinitionProperties(array $before, array $after, array &$changes): void
    {
        $beforeProperties = $this->withoutKeys($before, ['schema_version', 'fields', 'rules']);
        $afterProperties = $this->withoutKeys($after, ['schema_version', 'fields', 'rules']);

        $this->compareMap(
            $beforeProperties,
            $afterProperties,
            '/'.'',
            'definition_property_added',
            'definition_property_removed',
            'definition_property_changed',
            $changes,
        );
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<int, array<string, mixed>> $changes @param array<int, string> $limitations */
    private function compareFields(array $before, array $after, array &$changes, array &$limitations): string
    {
        $beforeHas = array_key_exists('fields', $before);
        $afterHas = array_key_exists('fields', $after);
        $beforeFields = $before['fields'] ?? null;
        $afterFields = $after['fields'] ?? null;

        if (! $beforeHas || ! $afterHas || ! is_array($beforeFields) || ! is_array($afterFields)
            || ! $this->isObjectLike($beforeFields) || ! $this->isObjectLike($afterFields)) {
            if ($beforeHas !== $afterHas || ! $this->valuesEqual($beforeFields, $afterFields)) {
                $change = ['type' => 'fields_structure_changed', 'path' => '/fields'];
                if ($beforeHas) {
                    $change['before'] = $beforeFields;
                }
                if ($afterHas) {
                    $change['after'] = $afterFields;
                }
                $changes[] = $change;
            }
            $limitations[] = 'fields_compared_as_value';

            return 'whole_value';
        }

        $this->compareMap(
            $beforeFields,
            $afterFields,
            '/fields',
            'field_added',
            'field_removed',
            'field_definition_changed',
            $changes,
        );

        return 'field_key';
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<int, array<string, mixed>> $changes @param array<int, string> $limitations */
    private function compareRules(array $before, array $after, array &$changes, array &$limitations): string
    {
        $beforeHas = array_key_exists('rules', $before);
        $afterHas = array_key_exists('rules', $after);
        $beforeRules = $before['rules'] ?? null;
        $afterRules = $after['rules'] ?? null;

        if (! $beforeHas || ! $afterHas || ! is_array($beforeRules) || ! is_array($afterRules)
            || ! array_is_list($beforeRules) || ! array_is_list($afterRules)) {
            if ($beforeHas !== $afterHas || ! $this->valuesEqual($beforeRules, $afterRules)) {
                $change = ['type' => 'rules_structure_changed', 'path' => '/rules'];
                if ($beforeHas) {
                    $change['before'] = $beforeRules;
                }
                if ($afterHas) {
                    $change['after'] = $afterRules;
                }
                $changes[] = $change;
            }
            $limitations[] = 'rules_compared_as_value';

            return 'whole_value';
        }

        if ($this->hasUniqueRuleIds($beforeRules) && $this->hasUniqueRuleIds($afterRules)) {
            $this->compareRulesById($beforeRules, $afterRules, $changes);

            return 'rule_id';
        }

        $limitations[] = 'rules_compared_by_position';
        $limitations[] = 'rule_order_not_identified';
        $this->compareRulesByPosition($beforeRules, $afterRules, $changes);

        return 'position';
    }

    /** @param array<int, mixed> $beforeRules @param array<int, mixed> $afterRules @param array<int, array<string, mixed>> $changes */
    private function compareRulesById(array $beforeRules, array $afterRules, array &$changes): void
    {
        $beforeById = $this->rulesById($beforeRules);
        $afterById = $this->rulesById($afterRules);
        $beforeIds = array_keys($beforeById);
        $afterIds = array_keys($afterById);
        sort($beforeIds, SORT_STRING);
        sort($afterIds, SORT_STRING);

        foreach (array_values(array_diff($afterIds, $beforeIds)) as $id) {
            $changes[] = [
                'type' => 'rule_added',
                'path' => '/rules/@'.$id,
                'rule_id' => $id,
                'after' => $afterById[$id],
            ];
        }
        foreach (array_values(array_diff($beforeIds, $afterIds)) as $id) {
            $changes[] = [
                'type' => 'rule_removed',
                'path' => '/rules/@'.$id,
                'rule_id' => $id,
                'before' => $beforeById[$id],
            ];
        }

        foreach (array_values(array_intersect($beforeIds, $afterIds)) as $id) {
            $beforeRule = $beforeById[$id];
            $afterRule = $afterById[$id];
            $keys = $this->sortedKeys(array_unique([...array_keys($beforeRule), ...array_keys($afterRule)]));

            foreach ($keys as $key) {
                if ($key === 'id') {
                    continue;
                }

                $beforeHas = array_key_exists($key, $beforeRule);
                $afterHas = array_key_exists($key, $afterRule);
                if ($beforeHas === $afterHas && (! $beforeHas || $this->valuesEqual($beforeRule[$key], $afterRule[$key]))) {
                    continue;
                }

                $type = match ($key) {
                    'effect' => 'rule_effect_changed',
                    'when' => 'rule_condition_changed',
                    default => $beforeHas ? 'rule_property_changed' : 'rule_property_added',
                };
                if (! $afterHas) {
                    $type = 'rule_property_removed';
                }

                $change = [
                    'type' => $type,
                    'path' => '/rules/@'.$id.'/'.self::escapePointerToken((string) $key),
                    'rule_id' => $id,
                ];
                if ($beforeHas) {
                    $change['before'] = $beforeRule[$key];
                }
                if ($afterHas) {
                    $change['after'] = $afterRule[$key];
                }
                $changes[] = $change;
            }
        }

        $commonIds = array_fill_keys(array_intersect($beforeIds, $afterIds), true);
        $beforeOrder = array_values(array_filter(array_map(static fn (array $rule): string => $rule['id'], $beforeRules), static fn (string $id): bool => isset($commonIds[$id])));
        $afterOrder = array_values(array_filter(array_map(static fn (array $rule): string => $rule['id'], $afterRules), static fn (string $id): bool => isset($commonIds[$id])));
        if ($beforeOrder !== $afterOrder) {
            $changes[] = [
                'type' => 'rules_reordered',
                'path' => '/rules',
                'before' => $beforeOrder,
                'after' => $afterOrder,
            ];
        }
    }

    /** @param array<int, mixed> $beforeRules @param array<int, mixed> $afterRules @param array<int, array<string, mixed>> $changes */
    private function compareRulesByPosition(array $beforeRules, array $afterRules, array &$changes): void
    {
        $count = max(count($beforeRules), count($afterRules));
        for ($index = 0; $index < $count; $index++) {
            $beforeHas = array_key_exists($index, $beforeRules);
            $afterHas = array_key_exists($index, $afterRules);
            if ($beforeHas && $afterHas && $this->valuesEqual($beforeRules[$index], $afterRules[$index])) {
                continue;
            }

            if (! $beforeHas || ! $afterHas) {
                $change = [
                    'type' => $beforeHas ? 'rule_removed' : 'rule_added',
                    'path' => '/rules/'.$index,
                    'rule_index' => $index,
                ];
                if ($beforeHas) {
                    $change['before'] = $beforeRules[$index];
                }
                if ($afterHas) {
                    $change['after'] = $afterRules[$index];
                }
                $changes[] = $change;
                continue;
            }

            if (is_array($beforeRules[$index]) && is_array($afterRules[$index])
                && $this->isObjectLike($beforeRules[$index]) && $this->isObjectLike($afterRules[$index])) {
                $beforeRule = $beforeRules[$index];
                $afterRule = $afterRules[$index];
                $keys = $this->sortedKeys(array_unique([...array_keys($beforeRule), ...array_keys($afterRule)]));
                foreach ($keys as $key) {
                    $beforeKeyHas = array_key_exists($key, $beforeRule);
                    $afterKeyHas = array_key_exists($key, $afterRule);
                    if ($beforeKeyHas === $afterKeyHas && (! $beforeKeyHas || $this->valuesEqual($beforeRule[$key], $afterRule[$key]))) {
                        continue;
                    }

                    $type = match ($key) {
                        'effect' => 'rule_effect_changed',
                        'when' => 'rule_condition_changed',
                        'id' => 'rule_id_changed',
                        default => $beforeKeyHas ? 'rule_property_changed' : 'rule_property_added',
                    };
                    if (! $afterKeyHas) {
                        $type = 'rule_property_removed';
                    }

                    $change = [
                        'type' => $type,
                        'path' => '/rules/'.$index.'/'.self::escapePointerToken((string) $key),
                        'rule_index' => $index,
                    ];
                    if (isset($beforeRule['id']) && is_string($beforeRule['id'])) {
                        $change['rule_id'] = $beforeRule['id'];
                    } elseif (isset($afterRule['id']) && is_string($afterRule['id'])) {
                        $change['rule_id'] = $afterRule['id'];
                    }
                    if ($beforeKeyHas) {
                        $change['before'] = $beforeRule[$key];
                    }
                    if ($afterKeyHas) {
                        $change['after'] = $afterRule[$key];
                    }
                    $changes[] = $change;
                }
                continue;
            }

            $change = [
                'type' => 'rule_changed',
                'path' => '/rules/'.$index,
                'rule_index' => $index,
                'before' => $beforeRules[$index],
                'after' => $afterRules[$index],
            ];
            $changes[] = $change;
        }
    }

    /** @param array<int|string, mixed> $before @param array<int|string, mixed> $after @param array<int, array<string, mixed>> $changes */
    private function compareMap(
        array $before,
        array $after,
        string $basePath,
        string $addedType,
        string $removedType,
        string $changedType,
        array &$changes,
    ): void {
        $keys = $this->sortedKeys(array_unique([...array_keys($before), ...array_keys($after)]));
        foreach ($keys as $key) {
            $beforeHas = array_key_exists($key, $before);
            $afterHas = array_key_exists($key, $after);
            if ($beforeHas && $afterHas && $this->valuesEqual($before[$key], $after[$key])) {
                continue;
            }

            $change = [
                'type' => $beforeHas ? ($afterHas ? $changedType : $removedType) : $addedType,
                'path' => rtrim($basePath, '/').'/'.self::escapePointerToken((string) $key),
            ];
            if ($beforeHas) {
                $change['before'] = $before[$key];
            }
            if ($afterHas) {
                $change['after'] = $after[$key];
            }
            $changes[] = $change;
        }
    }

    /** @param array<int|string, mixed> $definition @param array<int, string> $excluded @return array<int|string, mixed> */
    private function withoutKeys(array $definition, array $excluded): array
    {
        foreach ($excluded as $key) {
            unset($definition[$key]);
        }

        return $definition;
    }

    /** @param array<int, mixed> $rules */
    private function hasUniqueRuleIds(array $rules): bool
    {
        $seen = [];
        foreach ($rules as $rule) {
            if (! is_array($rule) || ! $this->isObjectLike($rule)
                || ! isset($rule['id']) || ! is_string($rule['id'])
                || preg_match('/^[a-z][a-z0-9._-]{0,79}$/D', $rule['id']) !== 1
                || isset($seen[$rule['id']])) {
                return false;
            }
            $seen[$rule['id']] = true;
        }

        return true;
    }

    /** @param array<int, array<string, mixed>> $rules @return array<string, array<string, mixed>> */
    private function rulesById(array $rules): array
    {
        $indexed = [];
        foreach ($rules as $rule) {
            $indexed[$rule['id']] = $rule;
        }

        return $indexed;
    }

    /** @param array<int, array<string, mixed>> $changes @return array<string, mixed> */
    private function summarize(array $changes): array
    {
        $summary = [
            'total_changes' => count($changes),
            'schema_version_changes' => 0,
            'definition_properties' => ['added' => 0, 'removed' => 0, 'changed' => 0],
            'fields' => ['added' => 0, 'removed' => 0, 'changed' => 0],
            'rules' => ['added' => 0, 'removed' => 0, 'modified' => 0, 'reordered' => false],
        ];
        $modifiedRules = [];

        foreach ($changes as $change) {
            $type = $change['type'];
            if ($type === 'schema_version_changed') {
                $summary['schema_version_changes']++;
            } elseif (str_starts_with($type, 'definition_property_')) {
                $kind = substr($type, strlen('definition_property_'));
                $summary['definition_properties'][$kind]++;
            } elseif (str_starts_with($type, 'field_')) {
                $kind = match ($type) {
                    'field_added' => 'added',
                    'field_removed' => 'removed',
                    default => 'changed',
                };
                $summary['fields'][$kind]++;
            } elseif ($type === 'rule_added') {
                $summary['rules']['added']++;
            } elseif ($type === 'rule_removed') {
                $summary['rules']['removed']++;
            } elseif ($type === 'rules_reordered') {
                $summary['rules']['reordered'] = true;
            } elseif ($type === 'rules_structure_changed') {
                $modifiedRules['rules_structure'] = true;
            } elseif (str_starts_with($type, 'rule_')) {
                $key = isset($change['rule_id']) ? 'id:'.$change['rule_id'] : 'index:'.($change['rule_index'] ?? $change['path']);
                $modifiedRules[$key] = true;
            }
        }

        $summary['rules']['modified'] = count($modifiedRules);

        return $summary;
    }

    /** @param array<int|string, mixed> $value */
    private function isObjectLike(array $value): bool
    {
        return $value === [] || ! array_is_list($value);
    }

    private function valuesEqual(mixed $before, mixed $after): bool
    {
        if (! is_array($before) || ! is_array($after)) {
            return get_debug_type($before) === get_debug_type($after) && $before === $after;
        }

        $beforeIsList = array_is_list($before);
        $afterIsList = array_is_list($after);
        if ($beforeIsList !== $afterIsList || count($before) !== count($after)) {
            return false;
        }

        if ($beforeIsList) {
            foreach ($before as $index => $value) {
                if (! $this->valuesEqual($value, $after[$index])) {
                    return false;
                }
            }

            return true;
        }

        foreach ($before as $key => $value) {
            if (! array_key_exists($key, $after) || ! $this->valuesEqual($value, $after[$key])) {
                return false;
            }
        }

        return true;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        $keys = $this->sortedKeys(array_keys($value));
        $canonical = [];
        foreach ($keys as $key) {
            $canonical[$key] = $this->canonicalize($value[$key]);
        }

        return $canonical;
    }

    /** @param array<int|string, mixed> $value */
    private function assertComparable(array $value): void
    {
        $nodes = 0;
        $this->assertJsonValue($value, 0, $nodes);

        try {
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(self::INPUT_INVALID);
        }
    }

    private function assertJsonValue(mixed $value, int $depth, int &$nodes): void
    {
        if ($depth > self::MAX_VALUE_DEPTH || ++$nodes > self::MAX_VALUE_NODES) {
            throw new InvalidArgumentException(self::INPUT_INVALID);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertJsonValue($item, $depth + 1, $nodes);
            }
            return;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)
            || (is_float($value) && is_finite($value))) {
            return;
        }

        throw new InvalidArgumentException(self::INPUT_INVALID);
    }

    /** @param array<int, mixed> $keys @return array<int, int|string> */
    private function sortedKeys(array $keys): array
    {
        usort($keys, static fn (int|string $left, int|string $right): int => strcmp((string) $left, (string) $right));

        return $keys;
    }

    private static function escapePointerToken(string $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $token);
    }
}
