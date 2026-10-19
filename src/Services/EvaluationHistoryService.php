<?php

namespace Modules\Glitter\PolicyEngine\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\EvaluationRepositoryInterface;

final class EvaluationHistoryService
{
    public function __construct(private readonly EvaluationRepositoryInterface $evaluations) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->evaluations->paginate($filters, $perPage);
    }
}
