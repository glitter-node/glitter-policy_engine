<?php

namespace Modules\Glitter\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Glitter\PolicyEngine\Casts\DefinitionJsonCast;

class PolicyRevision extends Model
{
    protected $table = 'glitter_policy_revisions';

    protected $fillable = [
        'policy_id', 'version', 'definition', 'definition_hash', 'definition_hash_version', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'definition' => DefinitionJsonCast::class,
            'definition_hash_version' => 'integer',
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
