<?php

// Called only after the existing verifier has authenticated the disposable MySQL schema.
// This helper does not alter the database or relax any pre-existing schema checks.
return static function ($schema, string $database): void {
    if ($database !== 'mobisttech_test' || ! $schema->hasTable('owner_offline_recovery_codes')) {
        throw new RuntimeException('Expected owner recovery table in disposable schema.');
    }

    $table = 'owner_offline_recovery_codes';
    $columns = $schema->getColumns($table);
    $names = array_column($columns, 'name');
    $expectedNames = ['id', 'admin_id', 'batch_id', 'code_digest', 'issued_at', 'used_at', 'revoked_at'];
    if ($names !== $expectedNames) {
        throw new RuntimeException('Owner recovery table columns differ from the migration contract.');
    }

    $definitions = [];
    foreach ($columns as $column) {
        $definitions[$column['name']] = $column;
    }
    foreach (['id', 'admin_id', 'batch_id', 'code_digest', 'issued_at'] as $required) {
        if ($definitions[$required]['nullable']) {
            throw new RuntimeException('Unexpected nullable owner recovery column: '.$required);
        }
    }
    foreach (['used_at', 'revoked_at'] as $optional) {
        if (! $definitions[$optional]['nullable']) {
            throw new RuntimeException('Missing nullable owner recovery column: '.$optional);
        }
    }

    $indexes = $schema->getIndexes($table);
    $byName = array_column($indexes, null, 'name');
    foreach (['PRIMARY', 'owner_offline_recovery_codes_code_digest_unique', 'owner_recovery_admin_used'] as $required) {
        if (! isset($byName[$required])) {
            throw new RuntimeException('Missing owner recovery index: '.$required);
        }
    }
    if ($byName['PRIMARY']['columns'] !== ['id'] || ! $byName['PRIMARY']['unique']
        || $byName['owner_offline_recovery_codes_code_digest_unique']['columns'] !== ['code_digest']
        || ! $byName['owner_offline_recovery_codes_code_digest_unique']['unique']
        || $byName['owner_recovery_admin_used']['columns'] !== ['admin_id', 'used_at']) {
        throw new RuntimeException('Owner recovery index definitions differ from the migration contract.');
    }

    $foreignKeys = $schema->getForeignKeys($table);
    $adminLink = array_filter($foreignKeys, static fn (array $key): bool =>
        $key['columns'] === ['admin_id']
        && $key['foreign_table'] === 'admins'
        && $key['foreign_columns'] === ['id']
        && strtoupper($key['on_delete']) === 'RESTRICT');
    if (count($adminLink) !== 1) {
        throw new RuntimeException('Owner recovery admin foreign key differs from the migration contract.');
    }

    $collations = DB::select('SELECT COLUMN_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?)', [$database, $table, 'batch_id', 'code_digest']);
    if (count($collations) !== 2) {
        throw new RuntimeException('Owner recovery digest/batch collation columns are missing.');
    }
    foreach ($collations as $column) {
        if ($column->COLLATION_NAME !== 'utf8mb4_bin') {
            throw new RuntimeException('Owner recovery binary collation is missing: '.$column->COLUMN_NAME);
        }
    }
};
