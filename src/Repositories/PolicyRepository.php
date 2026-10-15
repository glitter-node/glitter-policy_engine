<?php

namespace Modules\Glitter\PolicyEngine\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\PolicyEngine\Enums\PolicyStatus;
use Modules\Glitter\PolicyEngine\Exceptions\DraftConflictException;
use Modules\Glitter\PolicyEngine\Models\Policy;
use Modules\Glitter\PolicyEngine\Models\PolicyDraft;
use Modules\Glitter\PolicyEngine\Models\PolicyRevision;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\PolicyRepositoryInterface;

final class PolicyRepository implements PolicyRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Policy::query()->with('draft:id,policy_id,lock_version,updated_at');

        if (isset($filters['owner_extension'])) {
            $query->where('owner_extension', $filters['owner_extension']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($nested) use ($term) {
                $nested->where('policy_key', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage);
    }

    public function findOrFail(int $id): Policy
    {
        return Policy::query()->with(['draft', 'revisions' => fn ($query) => $query->orderByDesc('version')])->findOrFail($id);
    }

    public function findByIdentifier(string $ownerExtension, string $policyKey): ?Policy
    {
        return Policy::query()
            ->where('owner_extension', $ownerExtension)
            ->where('policy_key', $policyKey)
            ->first();
    }

    public function updateMetadata(Policy $policy, array $attributes): Policy
    {
        $policy->forceFill($attributes)->save();

        return $policy->fresh(['draft', 'revisions']);
    }

    public function createWithDraft(array $attributes, array $definition): Policy
    {
        return DB::transaction(function () use ($attributes, $definition) {
            $policy = Policy::query()->create($attributes);
            $policy->draft()->create([
                'definition' => $definition,
                'lock_version' => 0,
                'updated_by' => $attributes['created_by'] ?? null,
            ]);

            return $policy->load('draft');
        });
    }

    public function updateDraft(Policy $policy, array $definition, int $expectedLockVersion, ?int $userId): PolicyDraft
    {
        return DB::transaction(function () use ($policy, $definition, $expectedLockVersion, $userId) {
            $updated = PolicyDraft::query()
                ->where('policy_id', $policy->id)
                ->where('lock_version', $expectedLockVersion)
                ->update([
                    'definition' => $definition,
                    'lock_version' => $expectedLockVersion + 1,
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new DraftConflictException;
            }

            $policy->forceFill(['updated_by' => $userId])->save();

            return PolicyDraft::query()->where('policy_id', $policy->id)->firstOrFail();
        });
    }

    public function currentDraft(Policy $policy): PolicyDraft
    {
        return PolicyDraft::query()->where('policy_id', $policy->id)->firstOrFail();
    }

    public function publish(
        Policy $policy,
        array $definition,
        string $hash,
        int $expectedLockVersion,
        ?int $userId
    ): PolicyRevision {
        return DB::transaction(function () use ($policy, $definition, $hash, $expectedLockVersion, $userId) {
            $lockedPolicy = Policy::query()->lockForUpdate()->findOrFail($policy->id);
            $draft = PolicyDraft::query()->where('policy_id', $policy->id)->lockForUpdate()->firstOrFail();
            if ($draft->lock_version !== $expectedLockVersion || $draft->definition !== $definition) {
                throw new DraftConflictException;
            }

            $version = ((int) PolicyRevision::query()
                ->where('policy_id', $lockedPolicy->id)
                ->max('version')) + 1;

            $revision = PolicyRevision::query()->create([
                'policy_id' => $lockedPolicy->id,
                'version' => $version,
                'definition' => $definition,
                'definition_hash' => $hash,
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            $lockedPolicy->forceFill([
                'status' => PolicyStatus::Published,
                'published_version' => $version,
                'updated_by' => $userId,
            ])->save();

            return $revision;
        });
    }

    public function publishedRevision(Policy $policy): ?PolicyRevision
    {
        if ($policy->status !== PolicyStatus::Published || $policy->published_version === null) {
            return null;
        }

        return PolicyRevision::query()
            ->where('policy_id', $policy->id)
            ->where('version', $policy->published_version)
            ->first();
    }
}
