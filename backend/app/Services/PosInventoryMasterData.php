<?php

namespace App\Services;

use App\Models\PosMasterDataOption;
use App\Models\StockUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosInventoryMasterData
{
    public const LISTS = [
        'product_brand' => 'Brands',
        'product_category' => 'Product Categories',
        'product_subcategory' => 'Product Subcategories',
        'unit_color' => 'Colors',
        'device_ram_gb' => 'RAM Options',
        'device_storage_gb' => 'Storage Options',
        'device_sim_configuration' => 'SIM Configurations',
        'unit_condition' => 'Conditions',
        'unit_pta_status' => 'PTA Statuses',
        'unit_carrier_lock_status' => 'Carrier Lock Statuses',
        'unit_mdm_status' => 'MDM Statuses',
        'acquisition_source_type' => 'Buying Source Types',
    ];

    public function __construct(private readonly PosMasterData $masterData) {}

    public function lists(): array
    {
        return self::LISTS;
    }

    public function active(string $listKey): Collection
    {
        $this->assertManagedList($listKey);

        return $this->masterData->ordered($listKey);
    }

    public function all(string $listKey): Collection
    {
        $this->assertManagedList($listKey);

        return $this->masterData->ordered($listKey, true, true);
    }

    public function optionByCode(string $listKey, string $code): ?PosMasterDataOption
    {
        $this->assertManagedList($listKey);

        return PosMasterDataOption::query()
            ->where('list_key', $listKey)
            ->where('code', trim($code))
            ->first();
    }

    public function allFor(array $listKeys): Collection
    {
        foreach ($listKeys as $listKey) {
            $this->assertManagedList((string) $listKey);
        }

        return PosMasterDataOption::query()
            ->whereIn('list_key', $listKeys)
            ->orderBy('list_key')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(
        string $listKey,
        array $input,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $this->assertManagedList($listKey);
        [$code, $label, $metadata] = $this->createPayload($listKey, $input);
        $sortOrder = $this->sortOrder($input['sort_order'] ?? 0);

        return DB::transaction(function () use ($listKey, $code, $label, $metadata, $sortOrder, $actorType, $actorId) {
            $option = $this->masterData->createOption($listKey, $code, $label, $sortOrder, $actorType, $actorId);
            $option->metadata = $metadata;
            $option->save();

            return $option->refresh();
        });
    }

    public function update(
        PosMasterDataOption $option,
        array $input,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $this->assertManagedOption($option);
        $label = $this->editableLabel($option, $input);
        $sortOrder = $this->sortOrder($input['sort_order'] ?? $option->sort_order);
        $metadata = $option->metadata ?? [];

        if ($option->list_key === 'unit_color') {
            $metadata['hex'] = $this->hex($input['hex'] ?? data_get($metadata, 'hex'));
        }

        return DB::transaction(function () use ($option, $label, $sortOrder, $metadata, $actorType, $actorId) {
            $option = $this->masterData->updatePresentation($option, $label, $sortOrder, $actorType, $actorId);
            $option->metadata = $metadata;
            $option->save();

            return $option->refresh();
        });
    }

    public function setActive(PosMasterDataOption $option, bool $active, ?string $actorType = null, ?int $actorId = null): PosMasterDataOption
    {
        $this->assertManagedOption($option);

        return $this->masterData->setActive($option, $active, $actorType, $actorId);
    }

    public function archive(PosMasterDataOption $option, ?string $actorType = null, ?int $actorId = null): PosMasterDataOption
    {
        $this->assertManagedOption($option);

        return $this->masterData->archive($option, $actorType, $actorId);
    }

    public function restore(PosMasterDataOption $option, bool $activate, ?string $actorType = null, ?int $actorId = null): PosMasterDataOption
    {
        $this->assertManagedOption($option);

        return $this->masterData->restore($option, $activate, $actorType, $actorId);
    }

    public function delete(PosMasterDataOption $option): void
    {
        $this->assertManagedOption($option);

        if ($this->isCanonicalUnitStatusOption($option)) {
            throw new \LogicException('Canonical unit-status options cannot be permanently deleted. Deactivate or archive them instead.');
        }
        if ($this->isCanonicalAcquisitionSourceOption($option)) {
            throw new \LogicException('Canonical buying-source types cannot be permanently deleted. Deactivate or archive them instead.');
        }

        $this->masterData->deleteUnreferenced($option);
    }

    public function resolveSubmission(
        string $listKey,
        mixed $optionId,
        mixed $legacyValue = null,
        ?int $currentOptionId = null,
    ): ?PosMasterDataOption {
        $this->assertManagedList($listKey);

        if ($optionId !== null && $optionId !== '') {
            if (! ctype_digit((string) $optionId) || (int) $optionId < 1) {
                throw ValidationException::withMessages(['master_data' => 'The selected master-data option is invalid.']);
            }

            $option = PosMasterDataOption::query()->find((int) $optionId);
            if (! $option || $option->list_key !== $listKey) {
                throw ValidationException::withMessages(['master_data' => 'The selected master-data option does not belong to this field.']);
            }
            if ((! $option->is_active || $option->archived_at !== null) && (int) $option->id !== (int) $currentOptionId) {
                throw ValidationException::withMessages(['master_data' => 'The selected master-data option is inactive or archived.']);
            }

            return $option;
        }

        if ($legacyValue === null || trim((string) $legacyValue) === '') {
            return null;
        }

        $match = $this->active($listKey)->first(function (PosMasterDataOption $option) use ($listKey, $legacyValue) {
            return match ($listKey) {
                'device_ram_gb', 'device_storage_gb' => (int) data_get($option->metadata, 'value') === (int) $legacyValue,
                'device_sim_configuration',
                'product_category',
                'product_subcategory',
                'unit_condition',
                'unit_pta_status',
                'unit_carrier_lock_status',
                'unit_mdm_status',
                'acquisition_source_type' => $option->code === trim((string) $legacyValue),
                default => mb_strtolower(trim($option->label)) === mb_strtolower(trim((string) $legacyValue)),
            };
        });

        if ($match) {
            return $match;
        }

        throw ValidationException::withMessages(['master_data' => 'The submitted value is not an active managed option.']);
    }

    public function syncUsage(
        ?int $oldOptionId,
        ?PosMasterDataOption $newOption,
        string $usageType,
        string|int $usageId,
        string $usageField,
    ): void {
        if ($oldOptionId && (! $newOption || (int) $newOption->id !== $oldOptionId)) {
            $old = PosMasterDataOption::query()->find($oldOptionId);
            if ($old) {
                $this->masterData->forgetUsage($old, $usageType, $usageId, $usageField);
            }
        }

        if ($newOption) {
            $this->masterData->recordUsage($newOption, $usageType, $usageId, $usageField);
        }
    }

    public function value(PosMasterDataOption $option): int
    {
        $value = data_get($option->metadata, 'value');
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['master_data' => 'The selected numeric master-data option is corrupt.']);
        }

        return (int) $value;
    }

    public function imeiSlots(PosMasterDataOption $option): int
    {
        $slots = (int) data_get($option->metadata, 'imei_slots');
        if (! in_array($slots, [1, 2], true)) {
            throw ValidationException::withMessages(['master_data' => 'The selected SIM configuration has invalid IMEI-slot metadata.']);
        }

        return $slots;
    }

    public function subcategoryParentCode(PosMasterDataOption $option): string
    {
        $this->assertManagedOption($option);
        if ($option->list_key !== 'product_subcategory') {
            throw ValidationException::withMessages(['master_data' => 'The selected option is not a product subcategory.']);
        }

        $parent = trim((string) data_get($option->metadata, 'parent_category_code', ''));
        if (! in_array($parent, ['mobile_phone', 'tablet', 'accessory'], true)) {
            throw ValidationException::withMessages(['master_data' => 'The subcategory is missing a valid protected parent category.']);
        }

        return $parent;
    }

    public function acquisitionPartyKind(PosMasterDataOption $option): string
    {
        $this->assertManagedOption($option);
        if ($option->list_key !== 'acquisition_source_type') {
            throw ValidationException::withMessages(['master_data' => 'The selected option is not a buying-source type.']);
        }

        $partyKind = (string) data_get($option->metadata, 'party_kind', '');
        if (in_array($partyKind, ['business', 'individual'], true)) {
            return $partyKind;
        }

        return match ($option->code) {
            'individual_seller' => 'individual',
            'wholesaler', 'supplier', 'shop_dealer', 'other_business' => 'business',
            default => throw ValidationException::withMessages(['master_data' => 'The buying-source type is missing a valid party profile.']),
        };
    }

    private function createPayload(string $listKey, array $input): array
    {
        $label = trim((string) ($input['label'] ?? ''));

        if ($listKey === 'device_ram_gb' || $listKey === 'device_storage_gb') {
            $max = $listKey === 'device_ram_gb' ? 2048 : 8192;
            $value = filter_var($input['value'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
            if ($value === false) {
                throw ValidationException::withMessages(['value' => 'Enter a valid positive capacity value.']);
            }
            $prefix = $listKey === 'device_ram_gb' ? 'ram' : 'storage';
            $label = $listKey === 'device_storage_gb' && $value >= 1024 && $value % 1024 === 0
                ? ($value / 1024).' TB ('.$value.' GB)'
                : $value.' GB';

            return [$prefix.'_'.$value.'gb', $label, ['value' => $value]];
        }

        $label = $this->label($label);
        $code = Str::slug($label, '_');
        if ($code === '' || ! preg_match('/\A[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $code)) {
            throw ValidationException::withMessages(['label' => 'The label cannot produce a safe stable internal code.']);
        }

        if ($listKey === 'unit_color') {
            return [$code, $label, ['hex' => $this->hex($input['hex'] ?? null)]];
        }

        if ($listKey === 'device_sim_configuration') {
            $slots = filter_var($input['imei_slots'] ?? null, FILTER_VALIDATE_INT);
            if (! in_array($slots, [1, 2], true)) {
                throw ValidationException::withMessages(['imei_slots' => 'SIM configurations must require exactly 1 or 2 IMEI slots.']);
            }

            return [$code, $label, ['imei_slots' => $slots]];
        }

        if ($listKey === 'product_subcategory') {
            $parentCategoryCode = trim((string) ($input['parent_category_code'] ?? ''));
            if (! in_array($parentCategoryCode, ['mobile_phone', 'tablet', 'accessory'], true)) {
                throw ValidationException::withMessages(['parent_category_code' => 'Choose one protected top-level category for this subcategory.']);
            }

            return [$parentCategoryCode.'_'.$code, $label, ['parent_category_code' => $parentCategoryCode]];
        }

        if ($listKey === 'acquisition_source_type') {
            $partyKind = trim((string) ($input['party_kind'] ?? ''));
            if (! in_array($partyKind, ['business', 'individual'], true)) {
                throw ValidationException::withMessages(['party_kind' => 'Choose whether this source represents a business or a market/customer seller.']);
            }

            return [$code, $label, ['party_kind' => $partyKind]];
        }

        return [$code, $label, []];
    }

    private function editableLabel(PosMasterDataOption $option, array $input): string
    {
        if (in_array($option->list_key, ['device_ram_gb', 'device_storage_gb'], true)) {
            return $option->label;
        }

        return $this->label((string) ($input['label'] ?? $option->label));
    }

    private function label(string $label): string
    {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 160 || strip_tags($label) !== $label) {
            throw ValidationException::withMessages(['label' => 'Enter a plain-text label between 1 and 160 characters.']);
        }

        return $label;
    }

    private function hex(mixed $hex): string
    {
        $hex = strtoupper(trim((string) $hex));
        if (! preg_match('/\A#[0-9A-F]{6}\z/', $hex)) {
            throw ValidationException::withMessages(['hex' => 'Color swatches must use a six-digit HEX value such as #111111.']);
        }

        return $hex;
    }

    private function sortOrder(mixed $sortOrder): int
    {
        if (! is_numeric($sortOrder) || (int) $sortOrder < 0 || (int) $sortOrder > 100000) {
            throw ValidationException::withMessages(['sort_order' => 'Sort order must be between 0 and 100000.']);
        }

        return (int) $sortOrder;
    }

    private function isCanonicalUnitStatusOption(PosMasterDataOption $option): bool
    {
        $codes = match ($option->list_key) {
            'unit_condition' => array_keys(StockUnit::CONDITIONS),
            'unit_pta_status' => array_keys(StockUnit::PTA_STATUSES),
            'unit_carrier_lock_status' => array_keys(StockUnit::CARRIER_LOCK_STATUSES),
            'unit_mdm_status' => array_keys(StockUnit::MDM_STATUSES),
            default => [],
        };

        return in_array($option->code, $codes, true);
    }

    private function isCanonicalAcquisitionSourceOption(PosMasterDataOption $option): bool
    {
        return $option->list_key === 'acquisition_source_type'
            && in_array($option->code, ['wholesaler', 'supplier', 'shop_dealer', 'individual_seller', 'other_business'], true);
    }

    private function assertManagedOption(PosMasterDataOption $option): void
    {
        $this->assertManagedList($option->list_key);
    }

    private function assertManagedList(string $listKey): void
    {
        if (! array_key_exists($listKey, self::LISTS)) {
            throw ValidationException::withMessages(['list_key' => 'This inventory master-data list is not available in the current managed inventory scope.']);
        }
    }
}
