<?php

namespace Modules\Glitter\PolicyEngine\Http\Resources;

use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class PolicyCollection extends ResourceCollection
{
    public $collects = PolicyResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ],
            'abilities' => [
                'can_create' => PermissionHelper::check('glitter-policy_engine.policies.create'),
                'can_update' => PermissionHelper::check('glitter-policy_engine.policies.update'),
                'can_publish' => PermissionHelper::check('glitter-policy_engine.policies.publish'),
            ],
        ];
    }
}
