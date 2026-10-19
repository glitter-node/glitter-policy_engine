<?php

namespace Modules\Glitter\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Glitter\PolicyEngine\Casts\DefinitionJsonCast;

class PolicyDraft extends Model
{
    protected $table = 'glitter_policy_drafts';

    protected $fillable = ['policy_id', 'definition', 'lock_version', 'updated_by'];

    protected function casts(): array
    {
        return ['definition' => DefinitionJsonCast::class, 'lock_version' => 'integer'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}
