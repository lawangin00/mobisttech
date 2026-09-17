<?php

namespace App\Operations;

use App\Addendum\ResetDomains;
use App\Addendum\ResetRetention;
use App\Backups\RecoveryManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

final class ResetPlanner
{
    public const BREAKABLE_POINTERS = [
        'cms_policies' => ['current_revision_id'],
        'cms_policy_revisions' => ['restored_from_revision_id'],
        'site_managed_pages' => ['current_revision_id'],
        'site_navigation_items' => ['parent_id'],
        'site_page_revisions' => ['restored_from_revision_id'],
        'software_products' => ['current_revision_id', 'current_release_id'],
        'software_product_revisions' => ['restored_from_revision_id'],
        'pos_configuration_revisions' => ['restored_from_revision_id'],
    ];

    public function __construct(
        private ResetDomains $domains,
        private ResetRetention $retention,
        private RecoveryManifest $recovery,
    ) {}

    public function preview(string $level, array $domains): array
    {
        $domains = $this->domains->normalize($level, $domains);
        $selected = $this->domains->tables($level, $domains);
        $allTables = array_column(Schema::getTables(schema: DB::connection()->getDatabaseName()), 'name');
        $classification = $this->retention->classify($level, $allTables);
        $this->assertSelectedActions($level, $selected, $classification['tables']);
        $objects = $this->objectKeys($selected);
        $barriers = $this->barriers($selected);
        $order = $this->deletionOrder($selected);
        $byDomain = [];
        foreach ($domains as $domain) {
            $tables = array_values(array_intersect(ResetDomains::MAP[$domain], $selected));
            $records = 0;
            foreach ($tables as $table) {
                $records += $this->rowCount($table);
            }
            $byDomain[$domain] = [
                'tables' => $tables,
                'record_count' => $records,
                'file_count' => $this->objectCount($tables),
            ];
        }
        $recordCount = array_sum(array_column($byDomain, 'record_count'));
        $matrix = $this->preservationMatrix($classification['tables'], $selected);

        return [
            'contract' => 'mobisttech-reset-preview.v1',
            'level' => $level,
            'domains' => $domains,
            'domain_counts' => $byDomain,
            'record_count' => $recordCount,
            'file_count' => count($objects),
            'object_keys_sha256' => hash('sha256', json_encode($objects, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'deletion_order' => $order,
            'cycle_breaks' => $this->cycleBreaks($selected),
            'barriers' => $barriers,
            'preservation' => $matrix,
            'schema_sha256' => $this->recovery->schemaSha256(),
            'code_sha256' => $this->recovery->codeSha256(),
        ];
    }

    public function objectKeys(array $selectedTables): array
    {
        $keys = [];
        if (in_array('service_request_files', $selectedTables, true)) {
            $keys = [...$keys, ...DB::table('service_request_files')->whereNotNull('object_key')->pluck('object_key')->all()];
        }
        if (in_array('project_files', $selectedTables, true)) {
            $keys = [...$keys, ...DB::table('project_files')->whereNotNull('object_key')->pluck('object_key')->all()];
        }
        if (in_array('stock_acquisitions', $selectedTables, true)) {
            foreach (DB::table('stock_acquisitions')->get(['cnic_front_path', 'cnic_back_path']) as $row) {
                foreach (['cnic_front_path', 'cnic_back_path'] as $field) {
                    if (is_string($row->$field) && $row->$field !== '') {
                        $keys[] = $row->$field;
                    }
                }
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        return $keys;
    }

    public function rowCount(string $table): int
    {
        if ($table === 'users') {
            return DB::table('users')->where('is_admin', false)->count();
        }

        return DB::table($table)->count();
    }

    private function objectCount(array $tables): int
    {
        return count($this->objectKeys($tables));
    }

    private function assertSelectedActions(string $level, array $selected, array $classification): void
    {
        foreach ($selected as $table) {
            $action = $classification[$table]['action'] ?? null;
            $allowed = $action === 'candidate_clear'
                || ($table === 'users' && $action === 'row_selection_required')
                || ($level === 'factory' && $action === 'bootstrap_review'
                    && in_array($table, ResetDomains::MAP['factory_configuration'], true));
            if (! $allowed) {
                throw new LogicException('Reset domain selected a table without an approved clear rule: '.$table);
            }
        }
    }

    private function preservationMatrix(array $classification, array $selected): array
    {
        $result = [];
        foreach ($classification as $table => $policy) {
            $action = in_array($table, $selected, true) ? 'selected_clear' : $policy['action'];
            $key = $policy['group'].':'.$action;
            $result[$key] ??= ['group' => $policy['group'], 'action' => $action, 'tables' => []];
            $result[$key]['tables'][] = $table;
        }
        foreach ($result as &$entry) {
            sort($entry['tables'], SORT_STRING);
        }
        unset($entry);
        ksort($result, SORT_STRING);

        return array_values($result);
    }

    private function barriers(array $selected): array
    {
        $selectedSet = array_fill_keys($selected, true);
        $barriers = [];
        $database = DB::connection()->getDatabaseName();
        $tables = array_column(Schema::getTables(schema: $database), 'name');
        foreach ($tables as $child) {
            foreach (Schema::getForeignKeys($child) as $foreign) {
                $parent = $foreign['foreign_table'];
                if (! isset($selectedSet[$parent]) || isset($selectedSet[$child])) {
                    continue;
                }
                $count = $this->retainedReferenceCount($child, $parent, $foreign);
                if ($count > 0) {
                    $barriers[] = [
                        'type' => 'retained_reference',
                        'retained_child' => $child,
                        'selected_parent' => $parent,
                        'count' => $count,
                    ];
                }
            }
        }
        $this->unsafeStateBarriers($selectedSet, $barriers);

        return $barriers;
    }

    private function retainedReferenceCount(string $child, string $parent, array $foreign): int
    {
        $columns = $foreign['columns'];
        $parentColumns = $foreign['foreign_columns'];
        $query = DB::table($child.' as c')->join($parent.' as p', function ($join) use ($columns, $parentColumns) {
            foreach ($columns as $index => $column) {
                $join->on('c.'.$column, '=', 'p.'.$parentColumns[$index]);
            }
        });
        if ($parent === 'users') {
            $query->where('p.is_admin', false);
        }

        return $query->count();
    }

    private function unsafeStateBarriers(array $selectedSet, array &$barriers): void
    {
        if (isset($selectedSet['payments'])) {
            $count = DB::table('payments')->whereNotIn('status', ['completed', 'failed', 'cancelled', 'refunded'])
                ->orWhereNotNull('reconciliation_required_at')->count();
            if ($count > 0) {
                $barriers[] = ['type' => 'unsafe_payment_state', 'count' => $count];
            }
        }
        if (isset($selectedSet['inventory_custody_holds'])) {
            $count = DB::table('inventory_custody_holds')->whereNull('released_at')->count();
            if ($count > 0) {
                $barriers[] = ['type' => 'unsafe_stock_hold', 'count' => $count];
            }
        }
        if (isset($selectedSet['cash_sessions'])) {
            $count = DB::table('cash_sessions')->where('status', 'open')->count();
            if ($count > 0) {
                $barriers[] = ['type' => 'unsafe_open_cash_session', 'count' => $count];
            }
        }
    }

    private function cycleBreaks(array $selected): array
    {
        $selectedSet = array_fill_keys($selected, true);
        $result = [];
        foreach ($selected as $child) {
            foreach (Schema::getForeignKeys($child) as $foreign) {
                if (isset($selectedSet[$foreign['foreign_table']]) && $this->breakableEdge($child, $foreign)) {
                    $result[$child] = array_values(array_unique([...(array) ($result[$child] ?? []), ...$foreign['columns']]));
                }
            }
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    private function breakableEdge(string $child, array $foreign): bool
    {
        $allowed = self::BREAKABLE_POINTERS[$child] ?? [];

        return $allowed !== [] && array_diff($foreign['columns'], $allowed) === [];
    }

    private function deletionOrder(array $selected): array
    {
        $selectedSet = array_fill_keys($selected, true);
        $children = array_fill_keys($selected, []);
        foreach ($selected as $child) {
            foreach (Schema::getForeignKeys($child) as $foreign) {
                $parent = $foreign['foreign_table'];
                if (isset($selectedSet[$parent]) && ! $this->breakableEdge($child, $foreign)) {
                    $children[$parent][] = $child;
                }
            }
        }
        $temporary = [];
        $permanent = [];
        $order = [];
        $visit = function (string $table) use (&$visit, &$temporary, &$permanent, &$order, $children): void {
            if (isset($permanent[$table])) {
                return;
            }
            if (isset($temporary[$table])) {
                throw new LogicException('Selected reset scope contains a foreign-key cycle.');
            }
            $temporary[$table] = true;
            foreach ($children[$table] as $child) {
                $visit($child);
            }
            unset($temporary[$table]);
            $permanent[$table] = true;
            $order[] = $table;
        };
        foreach ($selected as $table) {
            $visit($table);
        }

        return $order;
    }
}
