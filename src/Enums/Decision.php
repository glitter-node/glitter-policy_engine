<?php

namespace Modules\Glitter\PolicyEngine\Enums;

enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Indeterminate = 'indeterminate';

    public function blocksExecution(): bool
    {
        return $this !== self::Allow;
    }
}
