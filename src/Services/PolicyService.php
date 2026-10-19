<?php

namespace Modules\Glitter\PolicyEngine\Services;

use App\Extension\HookManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Glitter\PolicyEngine\Data\RuleValidationResult;
use Modules\Glitter\PolicyEngine\Enums\PolicyStatus;
use Modules\Glitter\PolicyEngine\Exceptions\InvalidRuleDefinitionException;
use Modules\Glitter\PolicyEngine\Models\Policy;
use Modules\Glitter\PolicyEngine\Models\PolicyDraft;
use Modules\Glitter\PolicyEngine\Models\PolicyRevision;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\PolicyRepositoryInterface;
use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;
use Modules\Glitter\PolicyEngine\Support\DefinitionHasher;

final class PolicyService
{
    public function __construct(
        private readonly PolicyRepositoryInterface $policies,
        private readonly RuleDefinitionValidator $validator,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->policies->paginate($filters, $perPage);
    }

    public function findOrFail(int $id): Policy
    {
        return $this->policies->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Policy
    {
        $definition = $data['definition'] ?? $this->emptyDefinition();
        $policy = $this->policies->createWithDraft([
            'uuid' => (string) Str::uuid(),
            'owner_extension' => $data['owner_extension'],
            'policy_key' => $data['policy_key'],
            'description' => $data['description'] ?? null,
            'status' => PolicyStatus::Draft,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ], $definition);

        HookManager::doAction('glitter-policy_engine.policy.after_create', $policy);

        return $policy;
    }

    public function updateDraft(Policy $policy, array $definition, int $lockVersion): PolicyDraft
    {
        $draft = $this->policies->updateDraft($policy, $definition, $lockVersion, Auth::id());
        HookManager::doAction('glitter-policy_engine.policy.after_update', $policy, $draft);

        return $draft;
    }

    /** @param array<string, mixed> $data */
    public function updateMetadata(Policy $policy, array $data): Policy
    {
        $attributes = ['updated_by' => Auth::id()];
        if (array_key_exists('description', $data)) {
            $attributes['description'] = $data['description'];
        }
        if (array_key_exists('enabled', $data)) {
            $attributes['status'] = $data['enabled']
                ? ($policy->published_version === null ? PolicyStatus::Draft : PolicyStatus::Published)
                : PolicyStatus::Disabled;
        }

        $updated = $this->policies->updateMetadata($policy, $attributes);
        HookManager::doAction('glitter-policy_engine.policy.after_metadata_update', $updated);

        return $updated;
    }

    public function validateDraft(Policy $policy): RuleValidationResult
    {
        return $this->validator->validate(
            $this->policies->currentDraft($policy)->definition,
            rejectEmptyMembership: true,
            rejectAmbiguousEmptyArrayComparison: true,
            rejectUnverifiedArrayComparisonShape: true,
        );
    }

    public function publish(Policy $policy): PolicyRevision
    {
        $draft = $this->policies->currentDraft($policy);
        $validation = $this->validator->validate(
            $draft->definition,
            rejectEmptyMembership: true,
            rejectAmbiguousEmptyArrayComparison: true,
            rejectUnverifiedArrayComparisonShape: true,
        );
        if (! $validation->valid) {
            throw new InvalidRuleDefinitionException($validation->errors);
        }

        $revision = $this->policies->publish(
            $policy,
            $draft->definition,
            DefinitionHasher::hashForVersion($draft->definition, DefinitionHasher::VERSION_2),
            DefinitionHasher::VERSION_2,
            $draft->lock_version,
            Auth::id(),
        );
        HookManager::doAction('glitter-policy_engine.policy.after_publish', $policy->fresh(), $revision);

        return $revision;
    }

    /** @return array<string, mixed> */
    private function emptyDefinition(): array
    {
        return ['schema_version' => '1.0', 'fields' => ['action' => 'string'], 'rules' => []];
    }
}
