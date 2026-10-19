<?php

namespace Modules\Glitter\PolicyEngine\Support;

use JsonException;
use stdClass;

final class JsonObjectInputShape
{
    public static function isValidJson(string $rawBody): bool
    {
        try {
            json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * Return fields that are missing or are not JSON objects.
     * Null remains valid only for optional fields.
     *
     * @param array<int, string> $required
     * @param array<int, string> $optional
     * @return array<int, string>
     */
    public static function invalidFields(string $rawBody, array $required, array $optional = []): array
    {
        try {
            $payload = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return array_values(array_unique($required));
        }

        if (! $payload instanceof stdClass) {
            return array_values(array_unique($required));
        }

        $invalid = [];
        foreach ($required as $field) {
            if (! property_exists($payload, $field) || ! $payload->{$field} instanceof stdClass) {
                $invalid[] = $field;
            }
        }

        foreach ($optional as $field) {
            if (property_exists($payload, $field)
                && $payload->{$field} !== null
                && ! $payload->{$field} instanceof stdClass) {
                $invalid[] = $field;
            }
        }

        return array_values(array_unique($invalid));
    }

    /**
     * Check legacy non-JSON input without treating a PHP list as an object.
     *
     * @param array<string, mixed> $input
     * @param array<int, string> $required
     * @param array<int, string> $optional
     * @return array<int, string>
     */
    public static function invalidArrayFields(array $input, array $required, array $optional = []): array
    {
        $invalid = [];
        foreach ($required as $field) {
            if (! array_key_exists($field, $input)
                || ! is_array($input[$field])
                || array_is_list($input[$field])) {
                $invalid[] = $field;
            }
        }

        foreach ($optional as $field) {
            if (array_key_exists($field, $input)
                && $input[$field] !== null
                && (! is_array($input[$field]) || array_is_list($input[$field]))) {
                $invalid[] = $field;
            }
        }

        return array_values(array_unique($invalid));
    }
}
