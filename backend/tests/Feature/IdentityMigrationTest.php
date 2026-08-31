<?php

namespace Tests\Feature;

use App\Identity\IdentityImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private int $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->run = DB::table('migration_runs')->insertGetId(['input_manifest_hash' => str_repeat('1', 64), 'code_hash' => str_repeat('2', 64),
            'schema_hash' => str_repeat('3', 64), 'target_identity' => 'mobisttech_test', 'status' => 'identity_rehearsal', 'started_at' => now()]);
    }

    public function test_same_source_ids_and_emails_remain_separate_outlet_and_credential_identities(): void
    {
        $hash = Hash::make('SyntheticPass123!');
        foreach ([['pos', 'users'], ['pos', 'admins'], ['pos', 'super_admins'], ['website', 'users']] as [$source, $table]) {
            $row = $this->row($source, $table, ['id' => 7, 'email' => 'collision@example.invalid', 'password' => $hash, 'remember_token' => 'synthetic-old-token']);
            $result = $this->import($source, $table, $row);
            $this->assertSame('imported', $result['outcome'], json_encode($result));
        }
        foreach (['outlets', 'admins', 'super_admins', 'users'] as $table) {
            $this->assertSame(1, DB::table($table)->where('email', 'collision@example.invalid')->count());
        }
        $this->assertSame(1, DB::table('customers')->count());
        $this->assertSame(1, DB::table('customer_source_links')->count());
        foreach (['admins', 'super_admins', 'users'] as $table) {
            $this->assertSame($hash, DB::table($table)->value('password'));
            $this->assertNull(DB::table($table)->value('remember_token'));
        }
        $this->assertSame('source-account-identity', DB::table('customer_source_links')->value('verification_kind'));
        $this->assertSame(5, DB::table('migration_identity_map')->where('run_id', $this->run)->count());
    }

    public function test_identity_replay_is_idempotent_and_changed_input_or_email_collision_is_quarantined(): void
    {
        $row = $this->row('website', 'users', ['email' => 'unique@example.invalid']);
        $first = $this->import('website', 'users', $row);
        $second = $this->import('website', 'users', $row);
        $this->assertSame('already_imported', $second['outcome']);
        $this->assertSame($first['target_id'], $second['target_id']);
        $changed = $this->import('website', 'users', [...$row, 'name' => 'Changed']);
        $this->assertSame('quarantined', $changed['outcome']);
        $collision = $this->import('website', 'users', [...$row, 'id' => 2]);
        $this->assertSame('quarantined', $collision['outcome']);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('customers')->count());
        $this->assertSame(2, DB::table('migration_quarantine')->count());
        $this->assertSame('Synthetic', DB::table('users')->value('name'));
    }

    public function test_proven_legacy_owner_is_explicitly_mapped_and_audited_without_customer_identity(): void
    {
        $result = $this->import('website', 'users', $this->row('website', 'users', ['is_admin' => true, 'admin_role' => null]));
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $this->assertSame('owner', DB::table('users')->value('admin_role'));
        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('action', 'legacy_owner_mapped')->count());
    }

    public function test_invalid_privilege_values_are_quarantined_without_partial_accounts(): void
    {
        foreach ([['is_admin' => 'false'], ['is_admin' => true, 'admin_role' => 'root'], ['is_admin' => true, 'admin_role' => '0'], ['is_admin' => false, 'admin_role' => 'owner']] as $index => $extra) {
            $result = $this->import('website', 'users', $this->row('website', 'users', ['id' => $index + 1, ...$extra]));
            $this->assertSame('quarantined', $result['outcome'], json_encode($result));
        }
        $result = $this->import('pos', 'admins', $this->row('pos', 'admins', ['permissions' => ['invented.superpower']]));
        $this->assertSame('quarantined', $result['outcome']);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('admins')->count());
        $this->assertSame(0, DB::table('migration_identity_map')->count());
    }

    public function test_unknown_source_fields_fail_before_any_import_and_membership_uses_source_maps(): void
    {
        try {
            $this->import('website', 'users', [...$this->row('website', 'users'), 'unexpected_role' => 'owner']);
            $this->fail('Unknown source field accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, DB::table('users')->count());
        }
        $membership = $this->row('pos', 'shop_admins', ['shop_id' => 7, 'admin_id' => 9]);
        $this->assertSame('quarantined', $this->import('pos', 'shop_admins', $membership)['outcome']);
        $outlet = $this->import('pos', 'users', $this->row('pos', 'users', ['id' => 7]));
        $admin = $this->import('pos', 'admins', $this->row('pos', 'admins', ['id' => 9]));
        $this->assertSame('imported', $this->import('pos', 'shop_admins', $membership)['outcome']);
        $link = DB::table('outlet_admins')->first();
        $this->assertSame($outlet['target_id'], $link->outlet_id);
        $this->assertSame($admin['target_id'], $link->admin_id);
    }

    public function test_timestamps_hashes_and_unsupported_date_values_have_explicit_dispositions(): void
    {
        $row = $this->row('website', 'users', ['created_at' => '2026-08-31 12:00:00']);
        $result = app(IdentityImporter::class)->import($this->run, 'website', 'users', $row, 'Asia/Karachi');
        $this->assertSame('imported', $result['outcome']);
        $this->assertSame('2026-08-31 07:00:00.000000', DB::table('users')->value('created_at'));
        $changedTimezone = $this->import('website', 'users', $row);
        $this->assertSame('quarantined', $changedTimezone['outcome']);
        $this->assertSame('2026-08-31 07:00:00.000000', DB::table('users')->value('created_at'));
        $bad = $this->import('website', 'users', [...$row, 'id' => 2, 'created_at' => '2026-02-31 12:00:00']);
        $this->assertSame('quarantined', $bad['outcome']);
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_matching_unverified_contact_does_not_merge_a_customer_observation(): void
    {
        $observation = DB::table('customers')->insertGetId(['public_id' => (string) Str::uuid(),
            'display_name' => 'Unverified observation', 'email' => 'same@example.invalid', 'mobile' => '03000000001']);
        $result = $this->import('website', 'users', $this->row('website', 'users', ['email' => 'same@example.invalid', 'mobile' => '03000000001']));
        $this->assertSame('imported', $result['outcome']);
        $this->assertSame(2, DB::table('customers')->count());
        $this->assertNull(DB::table('customers')->where('id', $observation)->value('website_user_id'));
        $linked = DB::table('customers')->where('website_user_id', $result['target_id'])->first();
        $this->assertNotSame($observation, $linked->id);
        $this->assertSame(0, DB::table('customer_source_links')->where('customer_id', $observation)->count());
    }

    private function import(string $source, string $table, array $row): array
    {
        return app(IdentityImporter::class)->import($this->run, $source, $table, $row, 'UTC');
    }

    private function row(string $source, string $table, array $overrides = []): array
    {
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === $source && $entry['table'] === $table);
        $row = [];
        foreach ($entry['columns'] as $column) {
            $type = $column['target_type'];
            $row[$column['source_column']] = $type['nullable'] ? null : match ($type['type']) {
                'boolean' => false,
                'integer', 'bigInteger' => 1,
                default => 'Synthetic',
            };
        }
        foreach (['email' => 'synthetic@example.invalid', 'password' => Hash::make('SyntheticPass123!')] as $field => $value) {
            if (array_key_exists($field, $row)) {
                $row[$field] = $value;
            }
        }

        return [...$row, ...$overrides];
    }
}
