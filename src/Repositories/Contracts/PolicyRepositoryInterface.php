<?php

namespace Modules\Glitter\PolicyEngine\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\PolicyEngine\Models\Policy;
use Modules\Glitter\PolicyEngine\Models\PolicyDraft;
use Modules\Glitter\PolicyEngine\Models\PolicyRevision;

interface PolicyRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function findOrFail(int $id): Policy;

    public function findByIdentifier(string $ownerExtension, string $policyKey): ?Policy;

    /** @param array<string, mixed> $attributes */
    public function updateMetadata(Policy $policy, array $attributes): Policy;

    /** @param array<string, mixed> $attributes @param array<string, mixed> $definition */
    public function createWithDraft(array $attributes, array $definition): Policy;

    /** @param array<string, mixed> $definition */
    public function updateDraft(Policy $policy, array $definition, int $expectedLockVersion, ?int $userId): PolicyDraft;

    public function currentDraft(Policy $policy): PolicyDraft;

    /** @param array<string, mixed> $definition */
    public function publish(Policy $policy, array $definition, string $hash, int $expectedLockVersion, ?int $userId): PolicyRevision;

    public function publishedRevision(Policy $policy): ?PolicyRevision;
}
