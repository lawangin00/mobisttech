<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PosMasterDataRegistry
{
    public const LISTS = [
        'product_brand' => ['label' => 'Brands', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'product_category' => ['label' => 'Product Categories', 'allow_create' => false, 'allow_archive' => false, 'allow_delete' => false],
        'product_subcategory' => ['label' => 'Product Subcategories', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'unit_color' => ['label' => 'Colors', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'device_ram_gb' => ['label' => 'RAM Options', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'device_storage_gb' => ['label' => 'Storage Options', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'device_sim_configuration' => ['label' => 'SIM Configurations', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'unit_condition' => ['label' => 'Conditions', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'unit_pta_status' => ['label' => 'PTA Statuses', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'unit_carrier_lock_status' => ['label' => 'Carrier Lock Statuses', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'unit_mdm_status' => ['label' => 'MDM Statuses', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
        'acquisition_source_type' => ['label' => 'Buying Source Types', 'allow_create' => true, 'allow_archive' => true, 'allow_delete' => true],
    ];

    public function keys(): array
    {
        return array_keys(self::LISTS);
    }

    public function definition(string $listKey): array
    {
        if (! isset(self::LISTS[$listKey])) {
            throw ValidationException::withMessages(['list_key' => 'The requested POS master-data list is not supported.']);
        }

        return self::LISTS[$listKey];
    }
}
