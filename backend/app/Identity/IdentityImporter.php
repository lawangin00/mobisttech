<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

// Explicit, target-only identity rehearsal primitive. No source connection, HTTP route or file export exists here.
final class IdentityImporter
{
    public function import(int $runId, string $source, string $table, array $row, string $sourceTimezone): array
    {
        $supported = ['pos.users', 'pos.admins', 'pos.super_admins', 'pos.shop_admins', 'website.users'];
        if (! in_array($sourceTimezone, ['UTC', 'Asia/Karachi'], true)) {
            throw new InvalidArgumentException('Source timezone requires explicit reconciliation.');
        }
        if (! in_array($source.'.'.$table, $supported, true) || ! isset($row['id']) || ! ctype_digit((string) $row['id']) || (int) $row['id'] < 1) {
            throw new InvalidArgumentException('Unsupported source identity.');
        }
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run || $run->status !== 'identity_rehearsal' || $run->target_identity !== DB::connection()->getDatabaseName()) {
            throw new InvalidArgumentException('An explicit target identity rehearsal run is required.');
        }
        $websiteAdministrative = $source.'.'.$table === 'website.users'
            && (! in_array($row['is_admin'] ?? null, [false, 0], true) || ($row['admin_role'] ?? null) !== null);
        if (in_array($source.'.'.$table, ['pos.admins', 'pos.super_admins'], true) || $websiteAdministrative) {
            $digest = hash('sha256', json_encode(['source_timezone' => $sourceTimezone, 'row' => $row], JSON_THROW_ON_ERROR));
            DB::table('migration_quarantine')->insert(['run_id' => $runId, 'source_repository' => $source, 'source_table' => $table,
                'source_primary_key' => (string) $row['id'], 'reason' => 'Superseded administrative identity requires explicit verified Admin mapping.',
                'evidence_reference' => 'sha256:'.$digest]);

            return ['outcome' => 'quarantined', 'reason' => 'Superseded administrative identity requires explicit verified Admin mapping.'];
        }
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true, flags: JSON_THROW_ON_ERROR);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === $source && $entry['table'] === $table);
        $expected = array_column($entry['columns'], 'source_column');
        if (array_diff(array_keys($row), $expected) || array_diff($expected, array_keys($row))) {
            throw new InvalidArgumentException('Unknown or missing source identity columns.');
        }
        $target = $entry['target_table'];
        $identity = ['source_repository' => $source, 'source_table' => $table, 'source_primary_key' => (string) $row['id'], 'target_table' => $target];
        ksort($row);
        $digest = hash('sha256', json_encode(['source_timezone' => $sourceTimezone, 'row' => $row], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($runId, $source, $table, $row, $sourceTimezone, $entry, $target, $identity, $digest) {
                DB::table('migration_runs')->where('id', $runId)->lockForUpdate()->firstOrFail();
                $existing = DB::table('migration_identity_map')->where($identity)->first();
                if ($existing) {
                    if ($existing->row_digest !== $digest) {
                        throw new InvalidArgumentException('Changed source identity requires reconciliation.');
                    }

                    return ['outcome' => 'already_imported', 'target_table' => $target, 'target_id' => (int) $existing->target_id];
                }
                $values = [];
                foreach ($entry['columns'] as $column) {
                    $name = $column['source_column'];
                    if ($name === 'id') {
                        continue;
                    }
                    [, $destination] = explode('.', $column['destination']);
                    $value = $row[$name];
                    $type = $column['target_type'];
                    if ($value === null && ! $type['nullable']) {
                        throw new InvalidArgumentException('Required source field is null.');
                    }
                    if ($value !== null && in_array($type['type'], ['string', 'char'], true) && (! is_string($value) || mb_strlen($value) > ($type['length'] ?? 255))) {
                        throw new InvalidArgumentException('Source string exceeds its contract.');
                    }
                    if ($value !== null && $type['type'] === 'boolean' && ! in_array($value, [true, false, 0, 1], true)) {
                        throw new InvalidArgumentException('Invalid source boolean.');
                    }
                    if ($value !== null && in_array($type['type'], ['integer', 'bigInteger'], true)
                        && (! is_int($value) || (($type['unsigned'] ?? false) && $value < 0))) {
                        throw new InvalidArgumentException('Invalid source integer.');
                    }
                    if ($value !== null && $type['type'] === 'dateTime') {
                        if (! is_string($value)) {
                            throw new InvalidArgumentException('Invalid source timestamp type.');
                        }
                        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone($sourceTimezone));
                        if (! $date || $date->format('Y-m-d H:i:s') !== $value) {
                            throw new InvalidArgumentException('Invalid or unsupported source timestamp.');
                        }
                        $value = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                    }
                    if ($value !== null && $type['type'] === 'json') {
                        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
                        $value = json_encode($decoded, JSON_THROW_ON_ERROR);
                    }
                    $values[$destination] = $name === 'remember_token' ? null : $value;
                }
                $realm = $target === 'outlets' || $target === 'outlet_admins' ? 'pos' : 'customer';
                if (isset($values['password']) && ! in_array(password_get_info($values['password'])['algoName'], ['bcrypt', 'argon2i', 'argon2id'], true)) {
                    throw new InvalidArgumentException('Unsupported password hash.');
                }
                if ($target === 'admins' && $row['permissions'] !== null) {
                    $permissions = is_string($row['permissions']) ? json_decode($row['permissions'], true, flags: JSON_THROW_ON_ERROR) : $row['permissions'];
                    if (! is_array($permissions) || ! array_is_list($permissions)
                        || count(array_filter($permissions, 'is_string')) !== count($permissions)
                        || array_diff($permissions, array_keys(Admin::PERMISSIONS))) {
                        throw new InvalidArgumentException('Unknown POS permission.');
                    }
                }
                if ($target === 'outlet_admins') {
                    foreach (['shop_id' => ['users', 'outlets', 'outlet_id'], 'admin_id' => ['admins', 'admins', 'admin_id']] as $field => [$sourceTable, $targetTable, $targetColumn]) {
                        $mapped = $field === 'admin_id'
                            ? DB::table('admin_identity_mappings')->where(['source_repository' => 'pos', 'source_table' => $sourceTable,
                                'source_primary_key' => (string) $row[$field]])->value('admin_id')
                            : DB::table('migration_identity_map')->where(['source_repository' => 'pos', 'source_table' => $sourceTable,
                                'source_primary_key' => (string) $row[$field], 'target_table' => $targetTable])->value('target_id');
                        if (! $mapped) {
                            throw new InvalidArgumentException('Unresolved outlet membership identity.');
                        }
                        $values[$targetColumn] = (int) $mapped;
                    }
                } else {
                    $values['public_id'] = (string) Str::uuid();
                }
                $id = DB::table($target)->insertGetId($values);
                DB::table('migration_identity_map')->insert([...$identity, 'target_id' => (string) $id, 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'imported']);
                if ($target === 'users' && ! $row['is_admin']) {
                    $customer = app(CustomerIdentity::class)->forAccount(User::findOrFail($id));
                    DB::table('migration_identity_map')->insert([...$identity, 'target_table' => 'customers', 'target_id' => (string) $customer->id, 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'imported']);
                    DB::table('customer_source_links')->insert(['customer_id' => $customer->id, 'source_repository' => $source,
                        'source_table' => $table, 'source_primary_key' => (string) $row['id'], 'verification_kind' => 'source-account-identity',
                        'verified_actor_type' => 'migration_run', 'verified_actor_id' => $runId, 'verified_at' => now(), 'verification_reason' => 'Mapped original customer credential identity; no contact matching.']);
                }
                IdentityAudit::record($realm, $id, 'identity_imported', 'migration_run:'.$runId);

                return ['outcome' => 'imported', 'target_table' => $target, 'target_id' => $id];
            });
        } catch (QueryException|InvalidArgumentException|\JsonException $error) {
            $reason = $error instanceof QueryException ? 'Database identity collision or constraint violation.' : $error->getMessage();
            DB::table('migration_quarantine')->insert(['run_id' => $runId, 'source_repository' => $source, 'source_table' => $table,
                'source_primary_key' => (string) $row['id'], 'reason' => $reason, 'evidence_reference' => 'sha256:'.$digest]);

            return ['outcome' => 'quarantined', 'reason' => $reason];
        }
    }
}
