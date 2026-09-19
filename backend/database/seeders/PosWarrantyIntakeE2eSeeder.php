<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PosWarrantyIntakeE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            $outlet = DB::table('outlets')->where('outlet_code', 'E41')->whereNull('archived_at')->value('id');
            abort_unless($outlet && DB::table('admins')->where('email', 'e2e-intake@example.invalid')->exists(), 409);
            abort_unless(! DB::table('invoices')->where('invoice_number', 'like', 'MT75-INTAKE-%')->exists(), 409);
            abort_unless(! DB::table('products')->where('name', 'MT75 Intake Product')->exists(), 409);
            $product = DB::table('products')->insertGetId([
                'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet,
                'name' => 'MT75 Intake Product', 'category' => 'accessory',
                'price' => '1.00', 'purchase_price' => '1.00', 'sale_price' => '1.00',
                'warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30,
            ]);
            for ($n = 1; $n <= 125; $n++) {
                $invoice = DB::table('invoices')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet,
                    'invoice_number' => sprintf('MT75-INTAKE-%03d', $n),
                    'customer_name' => sprintf('MT75 Intake Customer %03d', $n),
                    'customer_phone' => '03001234567', 'customer_cnic' => '42101-1234567-1',
                    'total_bill' => '1.00', 'final_bill' => '1.00', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('sales')->insert([
                    'public_id' => (string) Str::uuid(), 'product_id' => $product, 'outlet_id' => $outlet,
                    'invoice_id' => $invoice, 'sale_date' => today(), 'sale_price' => '1.00',
                    'quantity' => 1, 'total_price' => '1.00', 'net_total_price' => '1.00',
                    'invoice_detail_snapshot' => json_encode(['warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }
}
