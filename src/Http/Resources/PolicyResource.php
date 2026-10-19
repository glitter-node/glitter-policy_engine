<?php

namespace Modules\Glitter\PolicyEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'owner_extension' => $this->owner_extension,
            'policy_key' => $this->policy_key,
            'description' => $this->description,
            'status' => $this->status->value,
            'published_version' => $this->published_version,
            'draft' => $this->whenLoaded('draft', fn () => [
                'definition' => $this->draft->definition,
                'lock_version' => $this->draft->lock_version,
                'updated_at' => $this->draft->updated_at?->toIso8601String(),
            ]),
            'revisions' => $this->whenLoaded('revisions', fn () => $this->revisions->map(fn ($revision) => [
                'version' => $revision->version,
                'definition_hash' => $revision->definition_hash,
                'published_at' => $revision->published_at?->toIso8601String(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
