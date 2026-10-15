<?php

namespace Modules\Glitter\PolicyEngine\Listeners;

use App\ActivityLog\Traits\ResolvesActivityLogType;
use App\Contracts\Extension\HookListenerInterface;
use Modules\Glitter\PolicyEngine\Models\Policy;
use Modules\Glitter\PolicyEngine\Models\PolicyDraft;
use Modules\Glitter\PolicyEngine\Models\PolicyRevision;

final class PolicyActivityLogListener implements HookListenerInterface
{
    use ResolvesActivityLogType;

    public static function getSubscribedHooks(): array
    {
        return [
            'glitter-policy_engine.policy.after_create' => ['method' => 'created', 'priority' => 20, 'sync' => true],
            'glitter-policy_engine.policy.after_update' => ['method' => 'updated', 'priority' => 20, 'sync' => true],
            'glitter-policy_engine.policy.after_metadata_update' => ['method' => 'metadataUpdated', 'priority' => 20, 'sync' => true],
            'glitter-policy_engine.policy.after_publish' => ['method' => 'published', 'priority' => 20, 'sync' => true],
        ];
    }

    public function handle(...$args): void
    {
        // 개별 훅 메서드에서 처리합니다.
    }

    public function created(Policy $policy): void
    {
        $this->write('policy.create', 'policy_create', $policy, ['status' => $policy->status->value]);
    }

    public function updated(Policy $policy, PolicyDraft $draft): void
    {
        $this->write('policy.update', 'policy_update', $policy, ['lock_version' => $draft->lock_version]);
    }

    public function published(Policy $policy, PolicyRevision $revision): void
    {
        $this->write('policy.publish', 'policy_publish', $policy, [
            'version' => $revision->version,
            'definition_hash' => $revision->definition_hash,
        ]);
    }

    public function metadataUpdated(Policy $policy): void
    {
        $this->write('policy.update', 'policy_metadata_update', $policy, ['status' => $policy->status->value]);
    }

    /** @param array<string, mixed> $properties */
    private function write(string $action, string $description, Policy $policy, array $properties): void
    {
        $this->logActivity($action, [
            'loggable' => $policy,
            'description_key' => "glitter-policy_engine::activity_log.description.{$description}",
            'description_params' => [
                'owner_extension' => $policy->owner_extension,
                'policy_key' => $policy->policy_key,
            ],
            'properties' => $properties,
        ]);
    }
}
