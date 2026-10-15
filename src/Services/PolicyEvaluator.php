<?php

namespace Modules\Glitter\PolicyEngine\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Glitter\PolicyEngine\Contracts\PolicyEvaluatorInterface;
use Modules\Glitter\PolicyEngine\Data\EvaluateRequest;
use Modules\Glitter\PolicyEngine\Data\EvaluateResult;
use Modules\Glitter\PolicyEngine\Enums\Decision;
use Modules\Glitter\PolicyEngine\Models\Policy;
use Modules\Glitter\PolicyEngine\Models\PolicyRevision;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\EvaluationRepositoryInterface;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\PolicyRepositoryInterface;
use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;
use RuntimeException;
use Throwable;

final class PolicyEvaluator implements PolicyEvaluatorInterface
{
    public function __construct(
        private readonly PolicyRepositoryInterface $policies,
        private readonly EvaluationRepositoryInterface $evaluations,
        private readonly RuleDefinitionValidator $validator,
    ) {}

    public function evaluate(EvaluateRequest $request): EvaluateResult
    {
        $startedAt = hrtime(true);
        $evaluationId = (string) Str::uuid();
        $policy = null;
        $revision = null;

        try {
            $this->assertRequestIsBounded($request);
            $policy = $this->policies->findByIdentifier($request->ownerExtension, $request->policyKey);
            if ($policy === null) {
                $result = $this->result(Decision::Deny, 'policy_not_found', null, $evaluationId);
            } else {
                $revision = $this->policies->publishedRevision($policy);
                $result = $revision === null
                    ? $this->result(Decision::Deny, 'published_revision_not_found', null, $evaluationId)
                    : $this->evaluateRevision($request, $revision, $evaluationId);
            }
        } catch (Throwable $exception) {
            Log::error('Policy evaluation failed safely.', [
                'evaluation_id' => $evaluationId,
                'owner_extension' => $request->ownerExtension,
                'policy_key' => $request->policyKey,
                'exception' => $exception::class,
            ]);
            $result = $this->result(Decision::Indeterminate, 'evaluation_error', $revision?->version, $evaluationId);
        }

        $durationUs = max(0, intdiv(hrtime(true) - $startedAt, 1000));

        try {
            $this->record($request, $result, $policy, $revision, $durationUs);
        } catch (Throwable $exception) {
            Log::error('Policy evaluation history write failed.', [
                'evaluation_id' => $evaluationId,
                'owner_extension' => $request->ownerExtension,
                'policy_key' => $request->policyKey,
                'exception' => $exception::class,
            ]);

            return $this->result(
                Decision::Indeterminate,
                'evaluation_history_write_failed',
                $result->policyVersion,
                $evaluationId,
                ['history_recorded' => false]
            );
        }

        return new EvaluateResult(
            $result->decision,
            $result->reasonCode,
            $result->policyVersion,
            $result->evaluationId,
            [...$result->metadata, 'history_recorded' => true, 'duration_us' => $durationUs],
        );
    }

    private function evaluateRevision(EvaluateRequest $request, PolicyRevision $revision, string $evaluationId): EvaluateResult
    {
        $definition = $revision->definition;
        $validation = $this->validator->validate($definition);
        if (! $validation->valid) {
            return $this->result(Decision::Indeterminate, 'published_revision_invalid', $revision->version, $evaluationId);
        }

        $input = [
            'subject' => $request->subject,
            'resource' => $request->resource,
            'action' => $request->action,
            'context' => $request->context,
        ];
        $matchedAllow = [];
        $matchedDeny = [];

        foreach ($definition['rules'] as $rule) {
            if (! $this->matches($rule['when'], $definition['fields'], $input)) {
                continue;
            }
            if ($rule['effect'] === 'deny') {
                $matchedDeny[] = $rule['id'];
            } else {
                $matchedAllow[] = $rule['id'];
            }
        }

        $metadata = [
            'matched_allow_rules' => $matchedAllow,
            'matched_deny_rules' => $matchedDeny,
            'rule_count' => count($definition['rules']),
        ];
        if ($matchedDeny !== []) {
            return $this->result(Decision::Deny, 'deny_rule_matched', $revision->version, $evaluationId, $metadata);
        }
        if ($matchedAllow !== []) {
            return $this->result(Decision::Allow, 'allow_rule_matched', $revision->version, $evaluationId, $metadata);
        }

        return $this->result(Decision::Deny, 'no_rule_matched', $revision->version, $evaluationId, $metadata);
    }

    /** @param array<string, mixed> $condition @param array<string, string> $fields @param array<string, mixed> $input */
    private function matches(array $condition, array $fields, array $input): bool
    {
        if (isset($condition['all'])) {
            foreach ($condition['all'] as $child) {
                if (! $this->matches($child, $fields, $input)) {
                    return false;
                }
            }
            return true;
        }
        if (isset($condition['any'])) {
            foreach ($condition['any'] as $child) {
                if ($this->matches($child, $fields, $input)) {
                    return true;
                }
            }
            return false;
        }

        [$exists, $actual] = $this->readField($input, $condition['field']);
        $operator = $condition['operator'];
        if ($operator === 'exists') {
            return $exists === $condition['value'];
        }
        if (! $exists) {
            throw new RuntimeException('A required evaluation field is missing.');
        }
        if (! $this->runtimeTypeMatches($actual, $fields[$condition['field']])) {
            throw new RuntimeException('An evaluation field has an invalid runtime type.');
        }

        $expected = $condition['value'];

        return match ($operator) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'gt' => $actual > $expected,
            'gte' => $actual >= $expected,
            'lt' => $actual < $expected,
            'lte' => $actual <= $expected,
            'in' => in_array($actual, $expected, true),
            'not_in' => ! in_array($actual, $expected, true),
            default => throw new RuntimeException('Unsupported operator reached the evaluator.'),
        };
    }

    /** @param array<string, mixed> $input @return array{0: bool, 1: mixed} */
    private function readField(array $input, string $path): array
    {
        $segments = explode('.', $path);
        $current = $input;
        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return [false, null];
            }
            $current = $current[$segment];
        }

        return [true, $current];
    }

    private function runtimeTypeMatches(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            default => false,
        };
    }

    private function assertRequestIsBounded(EvaluateRequest $request): void
    {
        if ($request->ownerExtension === '' || $request->policyKey === '' || $request->action === '') {
            throw new RuntimeException('Policy identifier and action are required.');
        }
        $payload = [$request->subject, $request->resource, $request->action, $request->context];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > RuleDefinitionValidator::MAX_JSON_BYTES) {
            throw new RuntimeException('Evaluation input exceeds the size limit.');
        }

        $nodes = 0;
        $this->assertValueIsBounded($payload, 0, $nodes);
    }

    private function assertValueIsBounded(mixed $value, int $depth, int &$nodes): void
    {
        if ($depth > RuleDefinitionValidator::MAX_DEPTH || ++$nodes > RuleDefinitionValidator::MAX_CONDITIONS) {
            throw new RuntimeException('Evaluation input exceeds structural limits.');
        }
        if (is_string($value) && strlen($value) > RuleDefinitionValidator::MAX_STRING_LENGTH) {
            throw new RuntimeException('Evaluation string exceeds the length limit.');
        }
        if (! is_array($value)) {
            return;
        }
        if (count($value) > RuleDefinitionValidator::MAX_LIST_ITEMS) {
            throw new RuntimeException('Evaluation collection exceeds the item limit.');
        }
        foreach ($value as $item) {
            $this->assertValueIsBounded($item, $depth + 1, $nodes);
        }
    }

    private function record(
        EvaluateRequest $request,
        EvaluateResult $result,
        ?Policy $policy,
        ?PolicyRevision $revision,
        int $durationUs
    ): void {
        $this->evaluations->create([
            'evaluation_uuid' => $result->evaluationId,
            'policy_id' => $policy?->id,
            'revision_id' => $revision?->id,
            'owner_extension' => $request->ownerExtension,
            'policy_key' => $request->policyKey,
            'policy_version' => $result->policyVersion,
            'decision' => $result->decision,
            'reason_code' => $result->reasonCode,
            'action' => $request->action,
            'evaluation_metadata' => $result->metadata,
            'duration_us' => $durationUs,
            'requested_by' => Auth::id(),
            'evaluated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function result(
        Decision $decision,
        string $reasonCode,
        ?int $version,
        string $evaluationId,
        array $metadata = []
    ): EvaluateResult {
        return new EvaluateResult($decision, $reasonCode, $version, $evaluationId, $metadata);
    }
}
