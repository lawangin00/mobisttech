<?php

namespace App\Inventory;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Infrastructure\PrivateObjects;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/** Private acquisition evidence only. No public URLs, client object paths or arbitrary file reads. */
final class AcquisitionDocuments
{
    public function attach(IdentityAccount $actor, Outlet $outlet, int $acquisitionId, string $side, string $bytes): void
    {
        $field = $this->field($side);
        $this->authorizedRow($actor, $outlet, $acquisitionId);
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Upload private evidence outside the stock transaction, then attach after commit.');
        }
        $image = strlen($bytes) <= 5 * 1024 * 1024 ? @getimagesizefromstring($bytes) : false;
        if (! $image || ! in_array($image['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new InvalidArgumentException('Acquisition evidence must be a JPEG or PNG no larger than 5 MB.');
        }
        $key = 'acquisitions/'.Str::uuid().($image['mime'] === 'image/png' ? '.png' : '.jpg');
        // A failure after storage may leave an unreferenced private immutable object; never expose or overwrite it.
        app(PrivateObjects::class)->put($key, $bytes, hash('sha256', $bytes));
        DB::transaction(function () use ($actor, $outlet, $acquisitionId, $field, $key) {
            $this->authorizedRow($actor, $outlet, $acquisitionId);
            DB::table('stock_acquisitions')->where('id', $acquisitionId)->lockForUpdate()->firstOrFail();
            DB::table('stock_acquisitions')->where('id', $acquisitionId)->update([$field => $key, 'updated_at' => now()]);
            IdentityAudit::record('admin', $actor->id, 'acquisition_evidence_attached', 'acquisition:'.$acquisitionId, $outlet->id);
        }, 3);
    }

    public function read(IdentityAccount $actor, Outlet $outlet, int $acquisitionId, string $side): string
    {
        $field = $this->field($side);
        $row = $this->authorizedRow($actor, $outlet, $acquisitionId);
        if (! $row->$field) {
            abort(404);
        }

        return app(PrivateObjects::class)->get($row->$field);
    }

    private function authorizedRow(IdentityAccount $actor, Outlet $outlet, int $id): object
    {
        $fresh = $actor->fresh();
        abort_unless($fresh && app(Access::class)->allows($fresh, 'shop.inventory', $outlet->fresh()), 403);

        return DB::table('stock_acquisitions')->where('id', $id)->where('outlet_id', $outlet->id)->firstOrFail();
    }

    private function field(string $side): string
    {
        if (! in_array($side, ['front', 'back'], true)) {
            throw new InvalidArgumentException('Unknown acquisition evidence side.');
        }

        return 'cnic_'.$side.'_path';
    }
}
