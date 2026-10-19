<?php

namespace Modules\Glitter\PolicyEngine\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Modules\Glitter\PolicyEngine\Exceptions\PolicyDiffException;
use Modules\Glitter\PolicyEngine\Models\Policy;
use Modules\Glitter\PolicyEngine\Models\PolicyDraft;
use Modules\Glitter\PolicyEngine\Models\PolicyRevision;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\PolicyRepositoryInterface;
use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;
use Modules\Glitter\PolicyEngine\Support\DefinitionHasher;
use Modules\Glitter\PolicyEngine\Support\PolicyDefinitionDiffer;
use Modules\Glitter\PolicyEngine\Support\PolicyDefinitionSafetyInspector;
use Modules\Glitter\PolicyEngine\Support\PolicyDatabaseErrorClassifier;
use InvalidArgumentException;
use LengthException;
use Throwable;
use UnexpectedValueException;

final class PolicyDiffService
{
    public function __construct(
        private readonly PolicyRepositoryInterface $policies,
        private readonly RuleDefinitionValidator $validator,
        private readonly PolicyDefinitionDiffer $differ,
    ) {}

    /**
     * @param array{type: string, version?: int} $from
     * @param array{type: string, version?: int} $to
     * @return array<string, mixed>
     */
    public function diff(int $policyId, array $from, array $to): array
    {
        $fromSelector = $this->validateSelector($from);
        $toSelector = $this->validateSelector($to);

        try {
            $policy = $this->policies->findForDiffOrFail($policyId);
            $fromSource = $this->resolveSource($policy, $fromSelector);
            $toSource = $this->resolveSource($policy, $toSelector);
        } catch (ModelNotFoundException) {
            throw new PolicyDiffException('policy_not_found');
        } catch (QueryException $exception) {
            throw new PolicyDiffException(
                PolicyDatabaseErrorClassifier::isSchemaNotReady($exception)
                    ? 'policy_schema_not_ready'
                    : 'policy_diff_failed'
            );
        }

        foreach ([$fromSource, $toSource] as $source) {
            if ($source['model'] instanceof PolicyRevision) {
                $this->assertRevisionIntegrity($source['model'], $source['definition']);
            }
        }

        $fromDiagnostics = $this->sourceDiagnostics($fromSource);
        $toDiagnostics = $this->sourceDiagnostics($toSource);

        try {
            $result = $this->differ->diff(
                $this->definitionArray($fromSource['definition']),
                $this->definitionArray($toSource['definition']),
            );
        } catch (LengthException $exception) {
            if ($exception->getMessage() === PolicyDefinitionDiffer::RESULT_TOO_LARGE) {
                throw new PolicyDiffException(PolicyDefinitionDiffer::RESULT_TOO_LARGE);
            }
            throw new PolicyDiffException('policy_diff_failed');
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === PolicyDefinitionDiffer::INPUT_INVALID) {
                throw new PolicyDiffException('policy_diff_definition_uncomparable');
            }
            throw new PolicyDiffException('policy_diff_failed');
        } catch (UnexpectedValueException $exception) {
            if ($exception->getMessage() === PolicyDefinitionDiffer::ENCODING_FAILED) {
                throw new PolicyDiffException(PolicyDefinitionDiffer::ENCODING_FAILED);
            }
            throw new PolicyDiffException('policy_diff_failed');
        }

        return [
            'policy_id' => $policy->id,
            'from' => $fromDiagnostics,
            'to' => $toDiagnostics,
            ...$result,
        ];
    }

    /** @param array<string, mixed> $selector @return array{type: string, version?: int} */
    private function validateSelector(array $selector): array
    {
        $type = $selector['type'] ?? null;
        if (! is_string($type) || ! in_array($type, ['published', 'revision', 'draft'], true)) {
            throw new PolicyDiffException('policy_diff_invalid_selector');
        }

        if ($type === 'revision') {
            if (array_diff(array_keys($selector), ['type', 'version']) !== []
                || ! array_key_exists('version', $selector)
                || ! is_int($selector['version'])
                || $selector['version'] < 1) {
                throw new PolicyDiffException('policy_diff_invalid_revision_version');
            }

            return ['type' => $type, 'version' => $selector['version']];
        }

        if (array_keys($selector) !== ['type']) {
            throw new PolicyDiffException('policy_diff_invalid_selector');
        }

        return ['type' => $type];
    }

    /** @param array{type: string, version?: int} $selector @return array{model: PolicyRevision|PolicyDraft, definition: mixed, metadata: array<string, mixed>} */
    private function resolveSource(Policy $policy, array $selector): array
    {
        try {
            $model = match ($selector['type']) {
                'published' => $this->policies->publishedRevisionForDiff($policy),
                'revision' => $this->policies->revision($policy, $selector['version']),
                'draft' => $this->policies->currentDraft($policy),
            };
        } catch (ModelNotFoundException) {
            if ($selector['type'] === 'draft') {
                throw new PolicyDiffException('policy_diff_draft_not_found');
            }
            throw new PolicyDiffException('policy_diff_revision_not_found');
        }

        if ($model === null) {
            throw new PolicyDiffException($selector['type'] === 'published'
                ? 'policy_diff_published_revision_not_found'
                : 'policy_diff_revision_not_found');
        }

        if ($model instanceof PolicyRevision) {
            try {
                $definition = $model->definition;
            } catch (UnexpectedValueException) {
                throw new PolicyDiffException('revision_integrity_failed');
            }

            return [
                'model' => $model,
                'definition' => $definition,
                'metadata' => [
                    'type' => $selector['type'],
                    'version' => $model->version,
                    'definition_hash' => $model->definition_hash,
                    'published_at' => $model->published_at?->toISOString(),
                ],
            ];
        }

        return [
            'model' => $model,
            'definition' => $model->definition,
            'metadata' => [
                'type' => 'draft',
                'lock_version' => $model->lock_version,
                'updated_at' => $model->updated_at?->toISOString(),
            ],
        ];
    }

    private function assertRevisionIntegrity(PolicyRevision $revision, mixed $definition): void
    {
        $hashVersion = $revision->definition_hash_version;
        $rawHashVersion = $revision->getRawOriginal('definition_hash_version');
        $expectedHashVersion = match ($rawHashVersion) {
            1, '1' => DefinitionHasher::VERSION_1,
            2, '2' => DefinitionHasher::VERSION_2,
            default => null,
        };
        if (! is_array($definition)
            || ! is_string($revision->definition_hash)
            || ! is_int($hashVersion)
            || $expectedHashVersion === null
            || $hashVersion !== $expectedHashVersion) {
            throw new PolicyDiffException('revision_integrity_failed');
        }

        try {
            $calculatedHash = DefinitionHasher::hashForVersion($definition, $hashVersion);
        } catch (Throwable) {
            throw new PolicyDiffException('revision_integrity_failed');
        }

        if (! hash_equals($revision->definition_hash, $calculatedHash)) {
            throw new PolicyDiffException('revision_integrity_failed');
        }
    }

    /** @return array{status: string} */
    private function validationStatus(mixed $definition): array
    {
        if (! is_array($definition)) {
            return ['status' => 'invalid'];
        }

        $validation = $this->validator->validate($definition);

        return ['status' => $validation->valid ? 'valid' : 'invalid'];
    }

    /** @param array{model: PolicyRevision|PolicyDraft, definition: mixed, metadata: array<string, mixed>} $source
     *  @return array<string, mixed>
     */
    private function sourceDiagnostics(array $source): array
    {
        $definition = $source['definition'];
        $isRevision = $source['model'] instanceof PolicyRevision;
        $diagnostics = [
            'source' => $source['metadata'],
            'validation' => $this->validationStatus($definition),
            'integrity' => ['status' => $isRevision ? 'verified' : 'not_applicable'],
            'safety' => PolicyDefinitionSafetyInspector::inspect(
                $definition,
                $this->validator,
                arrayComparisonShapeVerified: false,
            ),
        ];

        if (! $isRevision) {
            $diagnostics['publishability'] = [
                'status' => ! is_array($definition)
                    ? 'unknown'
                    : ($this->validator->validate(
                        $definition,
                        rejectEmptyMembership: true,
                        rejectAmbiguousEmptyArrayComparison: true,
                        rejectUnverifiedArrayComparisonShape: true,
                    )->valid
                        ? 'publishable'
                        : 'not_publishable'),
            ];
        }

        return $diagnostics;
    }

    private function definitionArray(mixed $definition): array
    {
        if (! is_array($definition)) {
            throw new PolicyDiffException('policy_diff_definition_uncomparable');
        }

        return $definition;
    }
}
