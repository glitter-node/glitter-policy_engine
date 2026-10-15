<?php

namespace Modules\Glitter\PolicyEngine\Exceptions;

use RuntimeException;

final class DraftConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Policy draft lock version does not match.');
    }
}
