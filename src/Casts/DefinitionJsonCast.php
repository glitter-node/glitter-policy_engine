<?php

namespace Modules\Glitter\PolicyEngine\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use Modules\Glitter\PolicyEngine\Support\DefinitionJsonEncoder;
use UnexpectedValueException;

final class DefinitionJsonCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException('Policy definition must be stored as JSON.');
        }

        try {
            $definition = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Unable to decode policy definition JSON.', 0, $exception);
        }

        if (! is_array($definition)) {
            throw new UnexpectedValueException('Policy definition JSON must decode to an array.');
        }

        return $definition;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new UnexpectedValueException('Policy definition must be an array.');
        }

        return DefinitionJsonEncoder::encode($value);
    }
}
