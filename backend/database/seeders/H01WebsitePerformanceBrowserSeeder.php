<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class H01WebsitePerformanceBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_H01_WEBSITE_PERF_E2E_ENABLED') === '1', 403);
        $receipt = base_path('../.local/h01-website-performance-browser-receipt.json');
        $mode = getenv('MT75_H01_WEBSITE_PERF_FIXTURE_ACTION');
        abort_unless(in_array($mode, ['seed', 'cleanup'], true), 403);
        if ($mode === 'seed') {
            abort_unless(! is_file($receipt) && DB::table('orders')->where('order_number', 'like', 'H01-PERFORMANCE-%')->doesntExist(), 409);
            $ids = DB::transaction(function () {
                $ids = [];
                foreach ([['commerce', '100.00', 'paid'], ['digital', '50.00', 'paid'], ['commerce', '75.00', 'paid_reconciliation']] as [$kind, $total, $status]) {
                    $ids[] = DB::table('orders')->insertGetId([
                        'order_number' => 'H01-PERFORMANCE-'.strtoupper($kind).'-'.Str::uuid(),
                        'order_type' => $kind, 'customer_name' => 'H01 Performance synthetic', 'customer_mobile' => '03000000000',
                        'subtotal' => $total, 'total' => $total, 'currency' => 'PKR', 'status' => $status === 'paid' ? 'confirmed' : 'pending',
                        'payment_status' => $status, 'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return $ids;
            });
            file_put_contents($receipt, json_encode(['ids' => $ids], JSON_THROW_ON_ERROR));
            $this->command?->info('H01 only-owned synthetic Website performance orders seeded.');

            return;
        }
        abort_unless(is_file($receipt), 409);
        $data = json_decode(file_get_contents($receipt), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($data['ids'] ?? null) && count($data['ids']) === 3, 409);
        $rows = DB::table('orders')->whereIn('id', $data['ids'])->get();
        abort_unless($rows->count() === 3 && $rows->every(fn ($row) => str_starts_with($row->order_number, 'H01-PERFORMANCE-')
            && $row->customer_name === 'H01 Performance synthetic' && $row->customer_mobile === '03000000000')
            && DB::table('order_items')->whereIn('order_id', $data['ids'])->doesntExist()
            && DB::table('payments')->whereIn('order_id', $data['ids'])->doesntExist(), 409);
        DB::table('orders')->whereIn('id', $data['ids'])->delete();
        unlink($receipt);
        $this->command?->info('H01 exact-owned synthetic Website performance orders cleaned.');
    }
}
