<?php

namespace Modules\Glitter\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyDraft extends Model
{
    protected $table = 'glitter_policy_drafts';

    protected $fillable = ['policy_id', 'definition', 'lock_version', 'updated_by'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'lock_version' => 'integer'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}
