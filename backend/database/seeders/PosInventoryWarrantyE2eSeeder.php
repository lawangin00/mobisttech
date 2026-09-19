<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PosInventoryWarrantyE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            $outlet = DB::table('outlets')->where('outlet_code', 'E41')->whereNull('archived_at')->value('id');
            abort_unless($outlet && DB::table('admins')->where('email', 'e2e-iw-warranty@example.invalid')->exists(), 409);
            abort_unless(DB::table('products')->where('name', 'like', 'MT75-IW Product %')->count() === 0, 409);
            abort_unless(DB::table('invoices')->where('invoice_number', 'like', 'MT75-IW-INV-%')->count() === 0, 409);
            abort_unless(DB::table('claims')->where('claim_number', 'like', 'MT75-IW-CLAIM-%')->count() === 0, 409);
            $product = null;
            for ($n = 1; $n <= 32; $n++) {
                $id = DB::table('products')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet,
                    'name' => sprintf('MT75-IW Product %03d', $n), 'price' => '1.00',
                    'sale_price' => '1.00', 'purchase_price' => '1.00', 'category' => 'accessory',
                    'warranty_type' => 'shop_warranty', 'qty' => 1,
                ]);
                if ($n === 1) $product = $id;
            }
            for ($n = 1; $n <= 22; $n++) {
                $invoice = DB::table('invoices')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet,
                    'invoice_number' => sprintf('MT75-IW-INV-%03d', $n),
                    'customer_name' => sprintf('MT75-IW Customer %03d', $n),
                    'customer_phone' => '03001234567', 'customer_cnic' => '42101-1234567-1',
                    'total_bill' => '1.00', 'final_bill' => '1.00',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('claims')->insert([
                    'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet,
                    'product_id' => $product, 'invoice_id' => $invoice,
                    'claim_number' => sprintf('MT75-IW-CLAIM-%03d', $n),
                    'status' => 'received', 'quantity' => 1, 'received_at' => now(),
                ]);
            }
        });
    }
}
