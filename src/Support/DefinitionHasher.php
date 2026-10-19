<?php

namespace Modules\Glitter\PolicyEngine\Support;

use InvalidArgumentException;
use RuntimeException;

final class DefinitionHasher
{
    public const VERSION_1 = 1;

    public const VERSION_2 = 2;

    private const VERSION_2_DOMAIN = 'glitter-policy-engine:definition-hash:v2';

    public static function hash(array $definition): string
    {
        return self::hashV1($definition);
    }

    public static function hashForVersion(array $definition, int $version): string
    {
        return match ($version) {
            self::VERSION_1 => self::hashV1($definition),
            self::VERSION_2 => self::hashV2($definition),
            default => throw new InvalidArgumentException('Unsupported policy definition hash version.'),
        };
    }

    private static function hashV1(array $definition): string
    {
        $normalized = self::normalize($definition);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode policy definition.');
        }

        return hash('sha256', $json);
    }

    private static function hashV2(array $definition): string
    {
        $normalized = self::normalize($definition);
        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );

        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode policy definition.');
        }

        return hash('sha256', self::VERSION_2_DOMAIN."\0".$json);
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
