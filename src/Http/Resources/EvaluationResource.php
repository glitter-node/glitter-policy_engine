<?php

namespace Modules\Glitter\PolicyEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'evaluation_id' => $this->evaluation_uuid,
            'owner_extension' => $this->owner_extension,
            'policy_key' => $this->policy_key,
            'policy_version' => $this->policy_version,
            'decision' => $this->decision->value,
            'reason_code' => $this->reason_code,
            'action' => $this->action,
            'metadata' => $this->evaluation_metadata,
            'duration_us' => $this->duration_us,
            'evaluated_at' => $this->evaluated_at?->toIso8601String(),
        ];
    }
}
