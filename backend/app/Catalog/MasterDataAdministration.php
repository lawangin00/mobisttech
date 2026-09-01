<?php

namespace App\Catalog;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Models\PosMasterDataOption;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MasterDataAdministration
{
    public function change(IdentityAccount $actor, string $action, string $list, array $input = [], ?int $id = null): ?PosMasterDataOption
    {
        return DB::transaction(function () use ($actor, $action, $list, $input, $id) {
            $actor = $actor->fresh();
            abort_unless($actor && app(Access::class)->allows($actor, 'config.master-data.manage'), 403);
            $allowed = match ($action) {
                'create' => ['label', 'sort_order', 'hex', 'value', 'imei_slots', 'parent_category_code', 'party_kind'],
                'update' => ['label', 'sort_order', 'hex'],
                'activate', 'deactivate', 'archive', 'restore', 'delete' => [],
                default => throw ValidationException::withMessages(['action' => 'Unknown master-data action.']),
            };
            if (array_diff(array_keys($input), $allowed)) {
                throw ValidationException::withMessages(['request' => 'Unexpected master-data fields; codes and actors are immutable server context.']);
            }
            $option = $id ? PosMasterDataOption::whereKey($id)->where('list_key', $list)->lockForUpdate()->firstOrFail() : null;
            abort_if($action !== 'create' && ! $option, 404);
            $service = app(PosInventoryMasterData::class);
            $realm = 'admin';
            $result = match ($action) {
                'create' => $service->create($list, $input, $realm, $actor->id),
                'update' => $service->update($option, $input, $realm, $actor->id),
                'activate', 'deactivate' => $service->setActive($option, $action === 'activate', $realm, $actor->id),
                'archive' => $service->archive($option, $realm, $actor->id),
                'restore' => $service->restore($option, false, $realm, $actor->id),
                'delete' => $this->delete($service, $option),
            };
            IdentityAudit::record($realm, $actor->id, 'master_data_'.$action, 'option:'.($result?->id ?? $id));
            CatalogChanged::record('master_data', (string) ($result?->id ?? $id), 1);

            return $result;
        }, 3);
    }

    private function delete(PosInventoryMasterData $service, PosMasterDataOption $option): null
    {
        $service->delete($option);

        return null;
    }
}
