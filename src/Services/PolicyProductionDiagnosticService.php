<?php

namespace Modules\Glitter\PolicyEngine\Services;

use Illuminate\Support\Facades\DB;
use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;
use Modules\Glitter\PolicyEngine\Support\PolicyDatabaseErrorClassifier;
use Modules\Glitter\PolicyEngine\Support\PolicyDefinitionImpactAnalyzer;
use Throwable;

final class PolicyProductionDiagnosticService
{
    private const TABLES = [
        'glitter_policy_policies',
        'glitter_policy_drafts',
        'glitter_policy_revisions',
        'glitter_policy_evaluations',
    ];

    public function __construct(private readonly RuleDefinitionValidator $validator) {}

    /**
     * Return a plan by default. Database reads require an explicit true flag.
     *
     * @return array<string, mixed>
     */
    public function diagnose(bool $execute = false, int $maxRevisions = 1000, float $maxSeconds = 30.0): array
    {
        if ($maxRevisions < 1 || $maxSeconds <= 0) {
            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_diagnostic_invalid_limits',
                'data' => [],
            ];
        }
        if (! $execute) {
            return $this->plan($maxRevisions, $maxSeconds);
        }

        try {
            return $this->runReadOnlyDiagnostic($maxRevisions, $maxSeconds);
        } catch (Throwable $exception) {
            $classification = $exception instanceof \Illuminate\Database\QueryException
                ? PolicyDatabaseErrorClassifier::classify($exception)
                : PolicyDatabaseErrorClassifier::UNKNOWN;

            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_diagnostic_failed',
                'error_classification' => $classification,
                'data' => [],
            ];
        }
    }

    /** @return array<string, mixed> */
    private function plan(int $maxRevisions, float $maxSeconds): array
    {
        return [
            'status' => 'not_run',
            'read_only' => true,
            'requires_explicit_execution' => true,
            'limits' => ['max_revisions' => $maxRevisions, 'max_seconds' => $maxSeconds],
            'checks' => [
                'schema_tables_and_definition_hash_version',
                'migration_record_visibility',
                'published_revision_counts',
                'hash_version_distribution',
                'revision_hash_integrity',
                'array_comparison_impact',
                'fail_closed_reason_counts',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function runReadOnlyDiagnostic(int $maxRevisions, float $maxSeconds): array
    {
        $connection = DB::connection();
        $database = $connection->selectOne('select database() as database_name');
        $databaseName = $this->rowValue($database, 'database_name');
        if (! is_string($databaseName) || $databaseName === '') {
            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_diagnostic_database_unavailable',
                'data' => [],
            ];
        }

        // INFORMATION_SCHEMA does not apply Laravel's connection prefix automatically.
        // Query the physical names while keeping the diagnostic contract keyed by the
        // module's logical table names.
        $tablePrefix = (string) $connection->getTablePrefix();
        $physicalTables = array_map(
            static fn (string $table): string => $tablePrefix.$table,
            self::TABLES,
        );
        $tablePlaceholders = implode(', ', array_fill(0, count($physicalTables), '?'));
        $tableRows = $connection->select(
            "select table_name from information_schema.tables where table_schema = ? and table_name in ({$tablePlaceholders})",
            [$databaseName, ...$physicalTables],
        );
        $tables = [];
        foreach ($tableRows as $row) {
            $name = $this->rowValue($row, 'table_name');
            if (! is_string($name)) {
                continue;
            }
            foreach (self::TABLES as $logicalTable) {
                if ($name === $tablePrefix.$logicalTable) {
                    $tables[$logicalTable] = true;
                    break;
                }
            }
        }

        $columns = [];
        if (isset($tables['glitter_policy_revisions'])) {
            $columnRows = $connection->select(
                'select column_name, data_type, is_nullable, column_default from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
                [$databaseName, $tablePrefix.'glitter_policy_revisions', 'definition_hash_version'],
            );
            foreach ($columnRows as $row) {
                $columns[] = [
                    'name' => $this->rowValue($row, 'column_name'),
                    'type' => $this->rowValue($row, 'data_type'),
                    'nullable' => $this->rowValue($row, 'is_nullable'),
                    'default' => $this->rowValue($row, 'column_default'),
                ];
            }
        }

        $migration = ['status' => 'unavailable'];
        try {
            $migration = [
                'status' => 'available',
                'definition_hash_version' => $connection->table('migrations')
                    ->where('migration', 'like', '%add_definition_hash_version_to_glitter_policy_revisions_table%')
                    ->exists(),
            ];
        } catch (Throwable) {
            $migration = ['status' => 'unavailable'];
        }

        $data = [
            'schema' => [
                'tables' => array_fill_keys(self::TABLES, false),
                'definition_hash_version' => $columns,
                'status' => 'unknown',
            ],
            'migration' => $migration,
            'published_revision_count' => 0,
            'hash_versions' => ['1' => 0, '2' => 0, 'other' => 0],
            'hash_integrity' => ['verified' => 0, 'failed' => 0],
            'array_comparison_impact' => 0,
            'reason_counts' => [],
            'published_revision_missing' => 0,
            'processed_revisions' => 0,
        ];
        foreach (self::TABLES as $table) {
            $data['schema']['tables'][$table] = isset($tables[$table]);
        }

        foreach (self::TABLES as $table) {
            if (! $data['schema']['tables'][$table]) {
                $data['schema']['status'] = 'missing';
                return [
                    'status' => 'partial_failure',
                    'read_only' => true,
                    'error_code' => 'policy_schema_not_ready',
                    'data' => $data,
                ];
            }
        }

        if ($columns === []) {
            $data['schema']['status'] = 'missing_column';
            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_schema_not_ready',
                'data' => $data,
            ];
        }
        $column = $columns[0];
        $columnReady = strtolower((string) ($column['type'] ?? '')) === 'int'
            && strtoupper((string) ($column['nullable'] ?? '')) === 'NO'
            && (string) ($column['default'] ?? '') === '1';
        $data['schema']['status'] = $columnReady ? 'ready' : 'mismatch';
        if (! $columnReady) {
            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_schema_mismatch',
                'data' => $data,
            ];
        }
        if (($migration['status'] ?? null) !== 'available') {
            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_migration_status_unavailable',
                'data' => $data,
            ];
        }
        if (($migration['definition_hash_version'] ?? false) !== true) {
            $data['schema']['status'] = 'migration_mismatch';
            return [
                'status' => 'partial_failure',
                'read_only' => true,
                'error_code' => 'policy_migration_not_applied',
                'data' => $data,
            ];
        }

        $startedAt = hrtime(true);
        $revisions = $connection->table('glitter_policy_policies as policies')
            ->leftJoin('glitter_policy_revisions as revisions', function ($join) {
                $join->on('policies.id', '=', 'revisions.policy_id')
                    ->on('policies.published_version', '=', 'revisions.version');
            })
            ->where('policies.status', 'published')
            ->whereNotNull('policies.published_version')
            ->select([
                'policies.id as policy_id',
                'revisions.id as revision_id',
                'revisions.definition',
                'revisions.definition_hash',
                'revisions.definition_hash_version',
            ])
            ->orderBy('policies.id')
            ->cursor();

        $lastPolicyId = null;
        $limited = false;
        foreach ($revisions as $revision) {
            if ((hrtime(true) - $startedAt) / 1_000_000_000 >= $maxSeconds
                || $data['processed_revisions'] >= $maxRevisions) {
                $limited = true;
                break;
            }
            $policyId = $revision->policy_id ?? null;
            if ($policyId === $lastPolicyId) {
                continue;
            }
            $lastPolicyId = $policyId;
            $data['processed_revisions']++;
            if ($revision->revision_id === null) {
                $data['published_revision_missing']++;
                continue;
            }
            $data['published_revision_count']++;
            $version = $revision->definition_hash_version ?? null;
            if ($version === 1 || $version === '1') {
                $data['hash_versions']['1']++;
            } elseif ($version === 2 || $version === '2') {
                $data['hash_versions']['2']++;
            } else {
                $data['hash_versions']['other']++;
            }

            $definition = $this->decodeDefinition($revision->definition ?? null);
            $impact = PolicyDefinitionImpactAnalyzer::analyzeRevision(
                $definition,
                $revision->definition_hash ?? null,
                is_int($version) ? $version : (is_numeric($version) ? (int) $version : $version),
                $this->validator,
            );
            if ($impact['integrity'] !== 'verified') {
                $data['hash_integrity']['failed']++;
                continue;
            }
            $data['hash_integrity']['verified']++;
            if ($impact['affected']) {
                $data['array_comparison_impact']++;
            }
            foreach ($impact['reason_codes'] as $reasonCode) {
                $data['reason_counts'][$reasonCode] = ($data['reason_counts'][$reasonCode] ?? 0) + 1;
            }
        }

        return [
            'status' => $limited ? 'incomplete' : 'completed',
            'read_only' => true,
            'limits' => ['max_revisions' => $maxRevisions, 'max_seconds' => $maxSeconds, 'limit_reached' => $limited],
            'data' => $data,
        ];
    }

    private function decodeDefinition(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
    }

    private function rowValue(mixed $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }
        if (is_object($row)) {
            return $row->{$key} ?? null;
        }
        return null;
    }
}
