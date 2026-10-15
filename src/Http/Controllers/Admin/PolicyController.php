<?php

namespace Modules\Glitter\PolicyEngine\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\PolicyEngine\Exceptions\DraftConflictException;
use Modules\Glitter\PolicyEngine\Exceptions\InvalidRuleDefinitionException;
use Modules\Glitter\PolicyEngine\Http\Requests\PolicyListRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\StorePolicyRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\UpdateDraftRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\UpdatePolicyRequest;
use Modules\Glitter\PolicyEngine\Http\Resources\PolicyCollection;
use Modules\Glitter\PolicyEngine\Http\Resources\PolicyResource;
use Modules\Glitter\PolicyEngine\Services\PolicyService;
use Throwable;

final class PolicyController extends AdminBaseController
{
    public function __construct(private readonly PolicyService $policies)
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
        } catch (DraftConflictException) {
            return $this->error('glitter-policy_engine::messages.draft.conflict', 409);
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-policy_engine::messages.policy.not_found');
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.policy.publish_failed', 500);
        }
    }
}
