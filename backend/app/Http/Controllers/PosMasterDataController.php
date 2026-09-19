<?php

namespace App\Http\Controllers;

use App\Catalog\MasterDataAdministration;
use App\Identity\Access;
use App\Models\Admin;
use App\Models\PosMasterDataOption;
use App\Services\PosInventoryMasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class PosMasterDataController extends Controller
{
    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        abort_unless(app(Access::class)->allows($actor, 'config.master-data.manage'), 403);
        return $actor;
    }

    public function index()
    {
        $this->actor();
        return response()->json(['data' => [
            'lists' => PosInventoryMasterData::LISTS,
            'options' => PosMasterDataOption::query()->withCount('usages')
                ->orderBy('list_key')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'list_key', 'code', 'label', 'is_active', 'archived_at', 'sort_order', 'metadata']),
        ]]);
    }

    public function change(Request $request, MasterDataAdministration $service)
    {
        $actor = $this->actor();
        $allowed = ['list', 'action', 'id', 'label', 'sort_order', 'hex', 'value', 'imei_slots', 'parent_category_code', 'party_kind'];
        if (array_diff(array_keys($request->all()), $allowed)) {
            throw ValidationException::withMessages(['request' => 'Unexpected master-data fields.']);
        }
        $data = $request->validate([
            'list' => ['required', 'string', 'in:'.implode(',', array_keys(PosInventoryMasterData::LISTS))],
            'action' => ['required', 'string', 'in:create,update,activate,deactivate,archive,restore,delete'],
            'id' => ['nullable', 'integer', 'min:1'], 'label' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'], 'hex' => ['sometimes', 'string', 'max:20'],
            'value' => ['sometimes', 'integer', 'min:0'], 'imei_slots' => ['sometimes', 'integer', 'min:1', 'max:2'],
            'parent_category_code' => ['sometimes', 'string', 'in:mobile_phone,tablet,accessory'],
            'party_kind' => ['sometimes', 'string', 'in:business,individual'],
        ]);
        $action = $data['action'];
        if (($action === 'create' && isset($data['id'])) || ($action !== 'create' && ! isset($data['id']))) {
            throw ValidationException::withMessages(['id' => 'Incorrect master-data action target.']);
        }
        $result = $service->change($actor, $action, $data['list'],
            array_diff_key($data, array_flip(['list', 'action', 'id'])), $data['id'] ?? null);
        return response()->json(['data' => ['id' => $result?->id, 'action' => $action]]);
    }
}
