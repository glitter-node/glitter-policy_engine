<?php

namespace Modules\Glitter\PolicyEngine\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\PolicyEngine\Models\PolicyEvaluation;

interface EvaluationRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): PolicyEvaluation;

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;
}
