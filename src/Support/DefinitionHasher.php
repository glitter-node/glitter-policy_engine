<?php

namespace Modules\Glitter\PolicyEngine\Support;

use RuntimeException;

final class DefinitionHasher
{
    public static function hash(array $definition): string
    {
        $normalized = self::normalize($definition);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode policy definition.');
        }

        return hash('sha256', $json);
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }
}
