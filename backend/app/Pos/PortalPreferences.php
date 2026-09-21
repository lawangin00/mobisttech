<?php

namespace App\Pos;

use App\Identity\IdentityAudit;
use App\Identity\OutletLifecycleAdministration;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PortalPreferences
{
    // Source inventory: global portal preferences, not individual operator credentials.
    private const OPTIONS = [
        'invoice_page_length' => ['10', '15', '25', '50', '100'],
        'inventory_page_length' => ['10', '15', '25', '50', '100'],
        'claims_page_length' => ['10', '15', '25', '50', '100'],
        'invoice_search_category' => ['all', 'invoice_id', 'customer_name', 'contact_number', 'item', 'imei', 'invoice_date', 'total'],
        'inventory_search_category' => ['all', 'product_name', 'sku', 'category', 'brand', 'model', 'variant', 'color', 'condition', 'pta_status', 'carrier_lock', 'mdm_status', 'purchase_price', 'sale_price', 'in_stock', 'sold', 'imei', 'warranty'],
        'warranty_search_category' => ['all', 'invoice_id', 'customer_name', 'customer_cnic', 'contact_number', 'product', 'imei'],
        'claims_search_category' => ['all', 'claim', 'invoice', 'customer', 'contact', 'product', 'imei', 'status', 'assigned'],
        'invoice_density' => ['comfortable', 'compact'],
        'inventory_density' => ['comfortable', 'compact'],
        'claims_density' => ['comfortable', 'compact'],
    ];

    private const DEFAULTS = [
        'invoice_page_length' => '15', 'inventory_page_length' => '10', 'claims_page_length' => '15',
        'invoice_search_category' => 'all', 'inventory_search_category' => 'all',
        'warranty_search_category' => 'all', 'claims_search_category' => 'all',
        'invoice_density' => 'comfortable', 'inventory_density' => 'comfortable', 'claims_density' => 'comfortable',
        'auto_focus_search' => true, 'remember_search' => false, 'navigation' => [],
    ];

    private const NAVIGATION_CUSTOMIZABLE = ['invoices', 'warranty', 'claims', 'master-data', 'profile', 'reports', 'operations'];

    public function canManage(Admin $actor): bool
    {
        return app(OutletLifecycleAdministration::class)->canManage($actor)
            && $actor->hasPermission('config.portal-presentation.manage');
    }

    public function authorize(Admin $actor): void
    {
        abort_unless($this->canManage($actor), 403);
    }

    public function current(): array
    {
        $stored = DB::table('pos_settings')->whereIn('key', array_map(fn ($key) => 'portal.'.$key, array_keys(self::DEFAULTS)))
            ->pluck('value', 'key');
        $values = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $fallback) {
            $raw = $stored['portal.'.$key] ?? null;
            if (is_array($fallback)) {
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                $values[$key] = is_array($decoded) ? $decoded : $fallback;
            } elseif (is_bool($fallback)) {
                $values[$key] = $raw === '1' ? true : ($raw === '0' ? false : $fallback);
            } elseif (is_string($raw) && in_array($raw, self::OPTIONS[$key], true)) {
                $values[$key] = $raw;
            }
        }

        return $values;
    }

    public function catalogueOptions(): array
    {
        return self::OPTIONS;
    }

    public function catalogue(Admin $actor): array
    {
        $this->authorize($actor);

        return ['values' => $this->current(), 'options' => self::OPTIONS];
    }

    public function update(Admin $actor, array $input): array
    {
        $this->authorize($actor);
        $allowed = array_keys(self::DEFAULTS);
        if (array_diff(array_keys($input), $allowed) || array_diff($allowed, array_keys($input))) {
            throw ValidationException::withMessages(['preferences' => 'Submit the complete approved portal preference set.']);
        }
        foreach (self::OPTIONS as $key => $options) {
            if (! is_string($input[$key]) || ! in_array($input[$key], $options, true)) {
                throw ValidationException::withMessages([$key => 'Invalid portal preference.']);
            }
        }
        foreach (['auto_focus_search', 'remember_search'] as $key) {
            if (! is_bool($input[$key])) {
                throw ValidationException::withMessages([$key => 'A true or false value is required.']);
            }
        }
        $input['navigation'] = $this->normalizeNavigation($input['navigation']);
        DB::transaction(function () use ($actor, $input) {
            $this->authorize($actor->fresh());
            foreach (self::DEFAULTS as $key => $default) {
                DB::table('pos_settings')->updateOrInsert(['key' => 'portal.'.$key], [
                    'value' => is_array($default) ? json_encode($input[$key], JSON_THROW_ON_ERROR) : (is_bool($default) ? ($input[$key] ? '1' : '0') : $input[$key]),
                    'group' => 'portal', 'label' => str_replace('_', ' ', $key),
                    'input_type' => is_bool($default) ? 'boolean' : 'select',
                    'sort_order' => 200 + array_search($key, array_keys(self::DEFAULTS), true),
                    'updated_at' => now(),
                ]);
            }
            IdentityAudit::record('admin', $actor->id, 'pos_portal_preferences_updated', 'portal:preferences');
        });

        return $this->current();
    }

    public function navigationCatalogue(): array
    {
        return collect(PosShell::areaDefinitions())->map(function (array $definition, string $key) {
            return ['key' => $key, 'default_label' => $definition['label'],
                'customizable' => in_array($key, self::NAVIGATION_CUSTOMIZABLE, true)];
        })->values()->all();
    }

    private function normalizeNavigation(mixed $submitted): array
    {
        if (! is_array($submitted)) {
            throw ValidationException::withMessages(['navigation' => 'Navigation presentation must be structured.']);
        }
        $definitions = PosShell::areaDefinitions();
        $result = [];
        foreach ($submitted as $key => $values) {
            if (! isset($definitions[$key]) || ! is_array($values) || array_diff(array_keys($values), ['label', 'visible', 'order'])) {
                throw ValidationException::withMessages(['navigation' => 'Navigation contains unsupported or protected metadata.']);
            }
            if (! in_array($key, self::NAVIGATION_CUSTOMIZABLE, true)) {
                if (($values['label'] ?? $definitions[$key]['label']) !== $definitions[$key]['label'] || ($values['visible'] ?? true) !== true) {
                    throw ValidationException::withMessages(["navigation.{$key}" => 'This core POS navigation item cannot be changed.']);
                }

                continue;
            }
            $label = trim((string) ($values['label'] ?? $definitions[$key]['label']));
            if ($label === '' || mb_strlen($label) > 40 || preg_match('/[<>\x00-\x1F\x7F]/u', $label)) {
                throw ValidationException::withMessages(["navigation.{$key}.label" => 'Use a plain navigation label between 1 and 40 characters.']);
            }
            $visible = filter_var($values['visible'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $order = filter_var($values['order'] ?? 100, FILTER_VALIDATE_INT);
            if ($visible === null || $order === false || $order < 100 || $order > 999) {
                throw ValidationException::withMessages(["navigation.{$key}" => 'Navigation visibility or order is invalid.']);
            }
            $result[$key] = ['label' => $label, 'visible' => $visible, 'order' => (int) $order];
        }

        return $result;
    }
}
