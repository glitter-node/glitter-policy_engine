<?php

namespace Modules\Glitter\PolicyEngine\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\PolicyEngine\Contracts\PolicyEvaluatorInterface;
use Modules\Glitter\PolicyEngine\Data\EvaluateRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\EvaluationListRequest;
use Modules\Glitter\PolicyEngine\Http\Requests\SimulatePolicyRequest;
use Modules\Glitter\PolicyEngine\Http\Resources\EvaluationCollection;
use Modules\Glitter\PolicyEngine\Services\EvaluationHistoryService;
use Throwable;

final class EvaluationController extends AdminBaseController
{
    public function __construct(
        private readonly PolicyEvaluatorInterface $evaluator,
        private readonly EvaluationHistoryService $history,
    ) {
        parent::__construct();
    }

    public function index(EvaluationListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $items = $this->history->paginate(
            array_filter($validated, fn ($value) => $value !== null && $value !== ''),
            $validated['per_page'] ?? 20,
        );

        return $this->success('glitter-policy_engine::messages.evaluation.fetch_success', new EvaluationCollection($items));
    }

    public function simulate(SimulatePolicyRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->evaluator->evaluate(new EvaluateRequest(
                ownerExtension: $validated['owner_extension'],
                policyKey: $validated['policy_key'],
                subject: $validated['subject'],
                resource: $validated['resource'],
                action: $validated['action'],
                context: $validated['context'] ?? [],
            ));

            return $this->success('glitter-policy_engine::messages.evaluation.complete', $result->toArray());
        } catch (Throwable) {
            return $this->error('glitter-policy_engine::messages.evaluation.failed', 500);
        }
    }
}
