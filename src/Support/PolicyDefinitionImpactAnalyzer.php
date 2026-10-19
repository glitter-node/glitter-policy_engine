<?php

namespace Modules\Glitter\PolicyEngine\Support;

use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;

final class PolicyDefinitionImpactAnalyzer
{
    /**
     * Analyze a definition without logging or returning its values.
     *
     * @return array{affected: bool, status: string, reason_codes: array<int, string>, condition_count: ?int}
     */
    public static function analyze(
        mixed $definition,
        RuleDefinitionValidator $validator,
        bool $arrayComparisonShapeVerified = false,
    ): array {
        $safety = PolicyDefinitionSafetyInspector::inspect(
            $definition,
            $validator,
            $arrayComparisonShapeVerified,
        );

        return [
            'affected' => $safety['status'] !== 'safe',
            'status' => $safety['status'],
            'reason_codes' => $safety['reason_codes'],
            'condition_count' => $safety['condition_count'],
        ];
    }

    /**
     * Verify a Revision before analyzing safety. Definition values are never returned.
     *
     * @return array{integrity: string, affected: bool, status: string, reason_codes: array<int, string>, condition_count: ?int}
     */
    public static function analyzeRevision(
        mixed $definition,
        mixed $storedHash,
        mixed $storedHashVersion,
        RuleDefinitionValidator $validator,
    ): array {
        if (! is_array($definition)
            || ! is_string($storedHash)
            || ! is_int($storedHashVersion)
            || ! in_array($storedHashVersion, [DefinitionHasher::VERSION_1, DefinitionHasher::VERSION_2], true)) {
            return [
                'integrity' => 'failed',
                'affected' => true,
                'status' => 'unknown',
                'reason_codes' => ['revision_integrity_failed'],
                'condition_count' => null,
            ];
        }

        try {
            $computedHash = DefinitionHasher::hashForVersion($definition, $storedHashVersion);
        } catch (\Throwable) {
            return [
                'integrity' => 'failed',
                'affected' => true,
                'status' => 'unknown',
                'reason_codes' => ['revision_integrity_failed'],
                'condition_count' => null,
            ];
        }

        if (! hash_equals($storedHash, $computedHash)) {
            return [
                'integrity' => 'failed',
                'affected' => true,
                'status' => 'unknown',
                'reason_codes' => ['revision_integrity_failed'],
                'condition_count' => null,
            ];
        }

        return [
            'integrity' => 'verified',
            ...self::analyze($definition, $validator),
        ];
    }
}
