<?php

namespace Modules\Glitter\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Glitter\PolicyEngine\Enums\Decision;

class PolicyEvaluation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'glitter_policy_evaluations';

    protected $fillable = [
        'evaluation_uuid', 'policy_id', 'revision_id', 'owner_extension', 'policy_key',
        'policy_version', 'decision', 'reason_code', 'action', 'evaluation_metadata',
        'duration_us', 'requested_by', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => Decision::class,
            'policy_version' => 'integer',
            'evaluation_metadata' => 'array',
            'duration_us' => 'integer',
            'evaluated_at' => 'immutable_datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(PolicyRevision::class, 'revision_id');
    }
}
