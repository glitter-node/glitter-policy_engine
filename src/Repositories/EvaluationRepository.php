<?php

namespace Modules\Glitter\PolicyEngine\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\PolicyEngine\Models\PolicyEvaluation;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\EvaluationRepositoryInterface;

final class EvaluationRepository implements EvaluationRepositoryInterface
{
    public function create(array $attributes): PolicyEvaluation
    {
        return PolicyEvaluation::query()->create($attributes);
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = PolicyEvaluation::query();

        foreach (['owner_extension', 'policy_key', 'decision', 'policy_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->orderByDesc('evaluated_at')->orderByDesc('id')->paginate($perPage);
    }
}
