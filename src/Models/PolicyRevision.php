<?php

namespace Modules\Glitter\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PolicyRevision extends Model
{
    protected $table = 'glitter_policy_revisions';

    protected $fillable = [
        'policy_id', 'version', 'definition', 'definition_hash', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'version' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published policy revisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Published policy revisions are immutable.'));
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}
