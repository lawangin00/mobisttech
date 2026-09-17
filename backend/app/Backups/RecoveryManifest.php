<?php

namespace App\Backups;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecoveryManifest
{
    public const CONTRACT = 'mobisttech-logical-backup.v2';

    public function keyId(): string
    {
        $configured = trim((string) config('backups.key_id'));
        if ($configured !== '') {
            if (! preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $configured)) {
                throw new RuntimeException('BACKUP_KEY_ID_INVALID');
            }

            return $configured;
        }

        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('RECOVERY_KEY_UNAVAILABLE');
        }

        return 'app-'.substr(hash('sha256', $key), 0, 24);
    }

    public function schemaSha256(): string
    {
        $database = DB::connection()->getDatabaseName();
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->orderBy('table_name')->orderBy('ordinal_position')
            ->get(['table_name', 'column_name', 'column_type', 'is_nullable', 'column_default', 'extra'])
            ->map(fn ($row) => (array) $row)->all();
        $indexes = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->orderBy('table_name')->orderBy('index_name')->orderBy('seq_in_index')
            ->get(['table_name', 'index_name', 'non_unique', 'seq_in_index', 'column_name'])
            ->map(fn ($row) => (array) $row)->all();
        $foreignKeys = DB::table('information_schema.key_column_usage')
            ->where('table_schema', $database)->whereNotNull('referenced_table_name')
            ->orderBy('table_name')->orderBy('constraint_name')->orderBy('ordinal_position')
            ->get(['table_name', 'constraint_name', 'column_name', 'referenced_table_name', 'referenced_column_name'])
            ->map(fn ($row) => (array) $row)->all();

        return $this->sha([
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ]);
    }

    public function codeSha256(): string
    {
        $files = [
            'backend-composer' => base_path('composer.lock'),
            'backend-npm' => base_path('package-lock.json'),            'website-npm' => base_path('../website/package-lock.json'),
            'target-schema' => base_path('../docs/schema/TARGET_SCHEMA.json'),
        ];
        $hashes = [];
        foreach ($files as $name => $path) {
            if (! is_file($path)) {
                throw new RuntimeException('RECOVERY_CODE_INPUT_MISSING');
            }
            $hashes[$name] = hash_file('sha256', $path);
        }

        return $this->sha($hashes);
    }

    public function backupManifest(array $tables): array
    {
        $tableManifest = [];
        foreach ($tables as $table => $rows) {
            $tableManifest[$table] = [
                'rows' => count($rows),
                'sha256' => $this->sha($rows),
            ];
        }
        ksort($tableManifest, SORT_STRING);

        return [
            'contract' => self::CONTRACT,
            'environment' => app()->environment(),
            'database' => DB::connection()->getDatabaseName(),
            'key_id' => $this->keyId(),
            'schema_sha256' => $this->schemaSha256(),            'code_sha256' => $this->codeSha256(),
            'tables' => $tableManifest,
        ];
    }

    public function sha(array $value): string
    {
        return hash('sha256', json_encode(
            $this->canonical($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ));
    }

    public function canonical(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(fn ($item) => is_array($item) ? $this->canonical($item) : $item, $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}
