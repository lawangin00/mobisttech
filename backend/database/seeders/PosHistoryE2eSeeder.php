<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PosHistoryE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            $outletId = DB::table('outlets')->where('outlet_code', 'E41')->value('id');
            abort_unless($outletId && DB::table('admins')->where('email', 'e2e-history@example.invalid')->exists(), 409);
            abort_unless(DB::table('invoices')->where('invoice_number', 'like', 'MT75-HIST-%')->count() === 0, 409);
            $rows = [];
            for ($n = 1; $n <= 122; $n++) {
                $rows[] = ['outlet_id' => $outletId, 'public_id' => (string) Str::uuid(),
                    'invoice_number' => sprintf('MT75-HIST-%03d', $n),
                    'customer_name' => sprintf('MT75 Synthetic Customer %03d', $n),
                    'customer_phone' => '03001234567', 'total_bill' => '1.00', 'final_bill' => '1.00',
                    'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('invoices')->insert($rows);
        });
    }
}
