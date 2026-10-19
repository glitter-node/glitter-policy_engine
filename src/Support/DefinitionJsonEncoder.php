<?php

namespace Modules\Glitter\PolicyEngine\Support;

use JsonException;
use UnexpectedValueException;

final class DefinitionJsonEncoder
{
    /** @param array<string, mixed> $definition */
    public static function encode(array $definition): string
    {
        try {
            return json_encode($definition, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Unable to encode policy definition JSON.', 0, $exception);
        }
    }
}
