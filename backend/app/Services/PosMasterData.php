<?php

namespace App\Services;

use App\Models\PosMasterDataOption;
use App\Models\PosMasterDataUsage;
use App\Support\PosMasterDataRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class PosMasterData
{
    public function __construct(private readonly PosMasterDataRegistry $registry) {}

    public function createOption(
        string $listKey,
        string $code,
        string $label,
        int $sortOrder = 0,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $definition = $this->registry->definition($listKey);
        if (! $definition['allow_create']) {
            throw new LogicException('This protected master-data list cannot accept arbitrary new internal codes.');
        }

        $code = $this->normalizeCode($code);
        if (PosMasterDataOption::query()->where('list_key', $listKey)->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'This master-data internal code already exists in the selected list.']);
        }
        $label = $this->normalizeLabel($label);
        $sortOrder = $this->normalizeSortOrder($sortOrder);
        [$actorType, $actorId] = $this->normalizeActor($actorType, $actorId);

        return PosMasterDataOption::create([
            'list_key' => $listKey,
            'code' => $code,
            'label' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_by_type' => $actorType,
            'created_by_id' => $actorId,
            'updated_by_type' => $actorType,
            'updated_by_id' => $actorId,
        ]);
    }

    public function updatePresentation(
        PosMasterDataOption $option,
        string $label,
        int $sortOrder,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $this->registry->definition($option->list_key);
        [$actorType, $actorId] = $this->normalizeActor($actorType, $actorId);

        $option->update([
            'label' => $this->normalizeLabel($label),
            'sort_order' => $this->normalizeSortOrder($sortOrder),
            'updated_by_type' => $actorType,
            'updated_by_id' => $actorId,
        ]);

        return $option->refresh();
    }

    public function setActive(
        PosMasterDataOption $option,
        bool $active,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $this->registry->definition($option->list_key);
        if ($active && $option->archived_at !== null) {
            throw ValidationException::withMessages(['is_active' => 'Restore the archived master-data option before activating it.']);
        }
        [$actorType, $actorId] = $this->normalizeActor($actorType, $actorId);

        $option->update([
            'is_active' => $active,
            'updated_by_type' => $actorType,
            'updated_by_id' => $actorId,
        ]);

        return $option->refresh();
    }

    public function archive(
        PosMasterDataOption $option,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $definition = $this->registry->definition($option->list_key);
        if (! $definition['allow_archive']) {
            throw new LogicException('This protected master-data list cannot be archived through the generic lifecycle.');
        }
        [$actorType, $actorId] = $this->normalizeActor($actorType, $actorId);

        $option->update([
            'is_active' => false,
            'archived_at' => now(),
            'updated_by_type' => $actorType,
            'updated_by_id' => $actorId,
        ]);

        return $option->refresh();
    }

    public function restore(
        PosMasterDataOption $option,
        bool $activate = false,
        ?string $actorType = null,
        ?int $actorId = null,
    ): PosMasterDataOption {
        $this->registry->definition($option->list_key);
        [$actorType, $actorId] = $this->normalizeActor($actorType, $actorId);

        $option->update([
            'archived_at' => null,
            'is_active' => $activate,
            'updated_by_type' => $actorType,
            'updated_by_id' => $actorId,
        ]);

        return $option->refresh();
    }

    public function ordered(string $listKey, bool $includeInactive = false, bool $includeArchived = false): Collection
    {
        $this->registry->definition($listKey);
        $query = PosMasterDataOption::query()->where('list_key', $listKey);

        if (! $includeArchived) {
            $query->whereNull('archived_at');
        }
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function recordUsage(
        PosMasterDataOption $option,
        string $usageType,
        string|int $usageId,
        string $usageField,
    ): PosMasterDataUsage {
        $this->registry->definition($option->list_key);
        [$usageType, $usageId, $usageField] = $this->normalizeUsage($usageType, $usageId, $usageField);

        return DB::transaction(function () use ($option, $usageType, $usageId, $usageField) {
            PosMasterDataOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            return PosMasterDataUsage::query()->firstOrCreate([
                'master_data_option_id' => $option->id,
                'usage_type' => $usageType,
                'usage_id' => $usageId,
                'usage_field' => $usageField,
            ]);
        });
    }

    public function forgetUsage(
        PosMasterDataOption $option,
        string $usageType,
        string|int $usageId,
        string $usageField,
    ): void {
        [$usageType, $usageId, $usageField] = $this->normalizeUsage($usageType, $usageId, $usageField);

        PosMasterDataUsage::query()
            ->where('master_data_option_id', $option->id)
            ->where('usage_type', $usageType)
            ->where('usage_id', $usageId)
            ->where('usage_field', $usageField)
            ->delete();
    }

    public function deleteUnreferenced(PosMasterDataOption $option): void
    {
        $definition = $this->registry->definition($option->list_key);
        if (! $definition['allow_delete']) {
            throw new LogicException('This protected master-data list cannot be permanently deleted.');
        }

        DB::transaction(function () use ($option) {
            $locked = PosMasterDataOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            if ($locked->usages()->exists()) {
                throw new LogicException('Referenced master-data options cannot be deleted; deactivate or archive them instead.');
            }

            $locked->delete();
        });
    }

    private function normalizeCode(string $code): string
    {
        $code = trim($code);
        if (! preg_match('/\A[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $code) || mb_strlen($code) > 100) {
            throw ValidationException::withMessages(['code' => 'Master-data internal codes must use lower snake_case and start with a letter.']);
        }

        return $code;
    }

    private function normalizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 160 || strip_tags($label) !== $label || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $label)) {
            throw ValidationException::withMessages(['label' => 'Master-data labels must be plain text between 1 and 160 characters.']);
        }

        return $label;
    }

    private function normalizeSortOrder(int $sortOrder): int
    {
        if ($sortOrder < 0 || $sortOrder > 100000) {
            throw ValidationException::withMessages(['sort_order' => 'Master-data sort order must be between 0 and 100000.']);
        }

        return $sortOrder;
    }

    private function normalizeActor(?string $actorType, ?int $actorId): array
    {
        if ($actorType === null && $actorId === null) {
            return [null, null];
        }
        if ($actorType === null || $actorId === null || $actorId < 1 || ! preg_match('/\A[a-z][a-z0-9_-]{0,39}\z/', $actorType)) {
            throw ValidationException::withMessages(['actor' => 'Master-data actor metadata is invalid.']);
        }

        return [$actorType, $actorId];
    }

    private function normalizeUsage(string $usageType, string|int $usageId, string $usageField): array
    {
        $usageType = trim($usageType);
        $usageId = trim((string) $usageId);
        $usageField = trim($usageField);

        if (! preg_match('/\A[a-zA-Z][a-zA-Z0-9_\\\\:-]{0,119}\z/', $usageType)) {
            throw ValidationException::withMessages(['usage_type' => 'Master-data usage type is invalid.']);
        }
        if ($usageId === '' || mb_strlen($usageId) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $usageId)) {
            throw ValidationException::withMessages(['usage_id' => 'Master-data usage identifier is invalid.']);
        }
        if (! preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $usageField)) {
            throw ValidationException::withMessages(['usage_field' => 'Master-data usage field is invalid.']);
        }

        return [$usageType, $usageId, $usageField];
    }
}
