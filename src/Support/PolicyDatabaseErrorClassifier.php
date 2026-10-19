<?php

namespace Modules\Glitter\PolicyEngine\Support;

use Illuminate\Database\QueryException;

final class PolicyDatabaseErrorClassifier
{
    public const SCHEMA_MISSING = 'schema_missing';
    public const DATABASE_MISSING = 'database_missing';
    public const UNKNOWN = 'unknown';

    /** @var array<string, true> */
    private const SCHEMA_SQL_STATES = [
        '42S02' => true, // table or view does not exist
        '42S22' => true, // column does not exist
        '42703' => true, // unknown column on PostgreSQL-compatible drivers
    ];

    /** @var array<int, true> */
    private const SCHEMA_DRIVER_CODES = [
        1054 => true, // unknown column
        1146 => true, // table does not exist
    ];

    public static function isSchemaNotReady(QueryException $exception): bool
    {
        return self::classify($exception) === self::SCHEMA_MISSING;
    }

    public static function classify(QueryException $exception): string
    {
        $sqlState = null;
        $driverCode = null;
        $errorInfo = $exception->errorInfo ?? null;
        if (is_array($errorInfo)) {
            $sqlState = is_string($errorInfo[0] ?? null) ? strtoupper($errorInfo[0]) : null;
            $driverCode = is_numeric($errorInfo[1] ?? null) ? (int) $errorInfo[1] : null;
        }

        if ($sqlState !== null && isset(self::SCHEMA_SQL_STATES[$sqlState])) {
            return self::SCHEMA_MISSING;
        }

        if ($driverCode === null && is_numeric($exception->getCode())) {
            $driverCode = (int) $exception->getCode();
        }

        if ($driverCode !== null && isset(self::SCHEMA_DRIVER_CODES[$driverCode])) {
            return self::SCHEMA_MISSING;
        }

        if ($sqlState === '3D000' || $driverCode === 1049) {
            return self::DATABASE_MISSING;
        }

        return self::UNKNOWN;
    }
}
