<?php

namespace Modules\Glitter\PolicyEngine\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\PolicyEngine\Exceptions\DraftConflictException;
use Modules\Glitter\PolicyEngine\Exceptions\InvalidRuleDefinitionException;
use Modules\Glitter\PolicyEngine\Exceptions\PolicyDiffException;
use Modules\Glitter\PolicyEngine\Exceptions\PolicySchemaNotReadyException;
use Modules\Glitter\PolicyEngine\Http\Requests\PolicyListRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\PolicyDiffRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\StorePolicyRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\UpdateDraftRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\UpdatePolicyRequest;
use Modules\Glitter\PolicyEngine\Http\Resources\PolicyCollection;
use Modules\Glitter\PolicyEngine\Http\Resources\PolicyResource;
use Modules\Glitter\PolicyEngine\Services\PolicyService;
use Modules\Glitter\PolicyEngine\Services\PolicyDiffService;
use Modules\Glitter\PolicyEngine\Support\PolicyDefinitionDiffer;
use Throwable;

final class PolicyController extends AdminBaseController
{
    public function __construct(
        private readonly PolicyService $policies,
        private readonly PolicyDiffService $diffs,
    )
    {
        parent::__construct();
    }

    public function index(PolicyListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $items = $this->policies->paginate(
            array_filter($validated, fn ($value) => $value !== null && $value !== ''),
            $validated['per_page'] ?? 20,
        );

        return $this->success('glitter-policy_engine::messages.policy.fetch_success', new PolicyCollection($items));
    }

    public function store(StorePolicyRequest $request): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-policy_engine::messages.policy.create_success',
                new PolicyResource($this->policies->create($request->validated())),
                201,
            );
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.policy.create_failed', 500);
        }
    }

    public function show(int $policy): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-policy_engine::messages.policy.fetch_success',
                new PolicyResource($this->policies->findOrFail($policy)),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-policy_engine::messages.policy.not_found');
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.policy.fetch_failed', 500);
        }
    }

    public function diff(PolicyDiffRequest $request, int $policy): JsonResponse
    {
        $validated = $request->validated();
        $from = ['type' => $validated['from']];
        $to = ['type' => $validated['to']];
        if (array_key_exists('from_version', $validated)) {
            $from['version'] = (int) $validated['from_version'];
        }
        if (array_key_exists('to_version', $validated)) {
            $to['version'] = (int) $validated['to_version'];
        }

        try {
            $response = $this->success(
                'glitter-policy_engine::messages.diff.success',
                $this->diffs->diff($policy, $from, $to),
            );

            $content = $response->getContent();
            if (! is_string($content)) {
                throw new \UnexpectedValueException('Unable to read serialized policy diff response.');
            }

            if (strlen($content) > PolicyDefinitionDiffer::MAX_RESULT_BYTES) {
                return $this->diffResultTooLargeResponse();
            }

            return $response;
        } catch (PolicyDiffException $exception) {
            [$message, $status] = $this->diffErrorResponse($exception->errorCode());

            return $this->error($message, $status, ['code' => $exception->errorCode()]);
        } catch (Throwable) {
            return $this->error(
                'glitter-policy_engine::messages.diff.failed',
                500,
                ['code' => 'policy_diff_failed'],
            );
        }
    }

    public function update(UpdatePolicyRequest $request, int $policy): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-policy_engine::messages.policy.update_success',
                new PolicyResource($this->policies->updateMetadata(
                    $this->policies->findOrFail($policy),
                    $request->validated(),
                )),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-policy_engine::messages.policy.not_found');
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.policy.update_failed', 500);
        }
    }

    public function updateDraft(UpdateDraftRequest $request, int $policy): JsonResponse
    {
        try {
            $model = $this->policies->findOrFail($policy);
            $draft = $this->policies->updateDraft(
                $model,
                $request->validated('definition'),
                $request->integer('lock_version'),
            );

            return $this->success('glitter-policy_engine::messages.draft.update_success', [
                'definition' => $draft->definition,
                'lock_version' => $draft->lock_version,
                'updated_at' => $draft->updated_at?->toIso8601String(),
            ]);
        } catch (DraftConflictException) {
            return $this->error('glitter-policy_engine::messages.draft.conflict', 409);
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-policy_engine::messages.policy.not_found');
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.draft.update_failed', 500);
        }
    }

    public function validateDraft(int $policy): JsonResponse
    {
        try {
            $result = $this->policies->validateDraft($this->policies->findOrFail($policy));

            return $this->success('glitter-policy_engine::messages.draft.validation_complete', $result->toArray());
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-policy_engine::messages.policy.not_found');
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.draft.validation_failed', 500);
        }
    }

    public function publish(int $policy): JsonResponse
    {
        try {
            $revision = $this->policies->publish($this->policies->findOrFail($policy));

            return $this->success('glitter-policy_engine::messages.policy.publish_success', [
                'version' => $revision->version,
                'definition_hash' => $revision->definition_hash,
                'published_at' => $revision->published_at?->toIso8601String(),
            ]);
        } catch (InvalidRuleDefinitionException $exception) {
            return $this->error(
                'glitter-policy_engine::messages.draft.invalid',
                422,
                ['definition' => $exception->errors()],
            );
        } catch (PolicySchemaNotReadyException) {
            return $this->error(
                'glitter-policy_engine::messages.policy.schema_not_ready',
                500,
                ['code' => PolicySchemaNotReadyException::ERROR_CODE],
            );
        } catch (DraftConflictException) {
            return $this->error('glitter-policy_engine::messages.draft.conflict', 409);
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-policy_engine::messages.policy.not_found');
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.policy.publish_failed', 500);
        }
    }

    /** @return array{0: string, 1: int} */
    private function diffErrorResponse(string $errorCode): array
    {
        return match ($errorCode) {
            'policy_not_found' => ['glitter-policy_engine::messages.policy.not_found', 404],
            'policy_diff_revision_not_found' => ['glitter-policy_engine::messages.diff.revision_not_found', 404],
            'policy_diff_draft_not_found' => ['glitter-policy_engine::messages.diff.draft_not_found', 404],
            'policy_diff_published_revision_not_found' => ['glitter-policy_engine::messages.diff.published_revision_not_found', 404],
            'policy_diff_invalid_selector' => ['glitter-policy_engine::messages.diff.invalid_selector', 422],
            'policy_diff_invalid_revision_version' => ['glitter-policy_engine::messages.diff.invalid_revision_version', 422],
            'policy_diff_definition_uncomparable' => ['glitter-policy_engine::messages.diff.definition_uncomparable', 422],
            'policy_definition_diff_result_too_large' => ['glitter-policy_engine::messages.diff.result_too_large', 422],
            'revision_integrity_failed' => ['glitter-policy_engine::messages.diff.integrity_failed', 500],
            'policy_definition_diff_encoding_failed' => ['glitter-policy_engine::messages.diff.encoding_failed', 500],
            'policy_schema_not_ready' => ['glitter-policy_engine::messages.diff.schema_not_ready', 500],
            default => ['glitter-policy_engine::messages.diff.failed', 500],
        };
    }

    private function diffResultTooLargeResponse(): JsonResponse
    {
        $errorCode = PolicyDefinitionDiffer::RESULT_TOO_LARGE;
        [$message, $status] = $this->diffErrorResponse($errorCode);
        $response = $this->error($message, $status, ['code' => $errorCode]);
        $content = $response->getContent();

        if (is_string($content) && strlen($content) <= PolicyDefinitionDiffer::MAX_RESULT_BYTES) {
            return $response;
        }

        return response()->json([
            'success' => false,
            'message' => '',
            'errors' => ['code' => $errorCode],
        ], $status);
    }
}
