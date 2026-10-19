<?php

namespace Modules\Glitter\PolicyEngine\Exceptions;

use RuntimeException;
use Throwable;

final class PolicySchemaNotReadyException extends RuntimeException
{
    public const ERROR_CODE = 'policy_schema_not_ready';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Policy Engine storage schema is not ready.', 0, $previous);
    }
}
