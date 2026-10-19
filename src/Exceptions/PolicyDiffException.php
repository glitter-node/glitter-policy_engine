<?php

namespace Modules\Glitter\PolicyEngine\Exceptions;

use RuntimeException;

final class PolicyDiffException extends RuntimeException
{
    public function __construct(private readonly string $diffErrorCode)
    {
        parent::__construct($diffErrorCode);
    }

    public function errorCode(): string
    {
        return $this->diffErrorCode;
    }
}
