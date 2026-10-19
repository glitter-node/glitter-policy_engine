<?php

namespace Modules\Glitter\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Glitter\PolicyEngine\Enums\PolicyStatus;

class Policy extends Model
{
    protected $table = 'glitter_policy_policies';

    protected $fillable = [
        'uuid', 'owner_extension', 'policy_key', 'description', 'status',
        'published_version', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'published_version' => 'integer',
        ];
    }

    public function draft(): HasOne
    {
        return $this->hasOne(PolicyDraft::class, 'policy_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PolicyRevision::class, 'policy_id');
    }
}
