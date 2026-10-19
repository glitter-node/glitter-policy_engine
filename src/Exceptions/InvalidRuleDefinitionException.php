<?php

namespace Modules\Glitter\PolicyEngine\Exceptions;

use RuntimeException;

final class InvalidRuleDefinitionException extends RuntimeException
{
    /** @param array<int, array{path: string, code: string}> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Policy rule definition is invalid.');
    }

    /** @return array<int, array{path: string, code: string}> */
    public function errors(): array
    {
        return $this->errors;
    }
}
