<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use App\Promotions\PromotionServices;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PromotionArchiveRowLockTest extends TestCase
{
    public function test_independent_promotion_retarget_waits_for_original_outlet_archive_lock(): void
    {
        // Committed synthetic fixture; source and production databases are never touched.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null;
        $actor = null;
        $promotionId = null;
        $writer = null;
        $payload = [
            'name' => 'Synthetic locked promotion', 'outlet_id' => null,
            'mode' => 'coupon', 'code' => 'ARCHIVELOCK'.strtoupper(str_replace('-', '', substr($tag, 0, 8))),
            'discount_type' => 'percentage', 'discount_value' => '10.00',
            'min_subtotal' => '0.00', 'customer_required' => false,
            'stackable' => false, 'priority' => 0, 'status' => 'active',
            'product_ids' => [], 'categories' => [],
        ];
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 promotion lock '.$tag, 'version' => 1, 'status' => false])->save();
            $actor = new Admin;
            $actor->forceFill(['name' => 'D03 promotion actor',
                'email' => 'd03-promotion-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password',
                'permissions' => ['config.promotions.manage']])->save();
            $configured = app(PromotionServices::class)->configure($actor, null,
                [...$payload, 'outlet_id' => $outlet->public_id]);
            $promotion = DB::table('promotions')->where('public_id', $configured['promotion_id'])->firstOrFail();
            $promotionId = $promotion->id;
            $events = DB::table('promotion_events')->where('promotion_id', $promotionId)->count();
            config(['database.connections.d03_promotion_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_promotion_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_promotion_writer');
                $writerActor = Admin::on('d03_promotion_writer')->findOrFail($actor->id);
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                try {
                    app(PromotionServices::class)->configure($writerActor,
                        $promotion->public_id, $payload);
                    $this->fail('Promotion retarget bypassed the archive-held outlet row.');
                } catch (QueryException $error) {
                    $this->assertSame(1205, (int) ($error->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) {
                    DB::connection('mysql')->rollBack();
                }
            }
            DB::setDefaultConnection('d03_promotion_writer');
            try {
                app(PromotionServices::class)->configure($writerActor,
                    $promotion->public_id, $payload);
                $this->fail('Archived origin promotion was retargeted after commit.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $retained = DB::table('promotions')->where('id', $promotionId)->firstOrFail();
            $this->assertSame($outlet->id, (int) $retained->outlet_id);
            $this->assertSame($promotion->version, $retained->version);
            $this->assertSame($events, DB::table('promotion_events')->where('promotion_id', $promotionId)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) {
                DB::connection('mysql')->rollBack();
            }
            if ($writer) {
                $writer->disconnect();
                DB::purge('d03_promotion_writer');
            }
            if ($promotionId) {
                DB::table('promotion_events')->where('promotion_id', $promotionId)->delete();
                DB::table('promotion_products')->where('promotion_id', $promotionId)->delete();
                DB::table('promotion_categories')->where('promotion_id', $promotionId)->delete();
                DB::table('promotions')->where('id', $promotionId)->delete();
            }
            if ($actor) {
                DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($outlet) {
                DB::table('outlets')->where('id', $outlet->id)
                    ->where('name', 'D03 promotion lock '.$tag)->delete();
            }
        }
    }
}
