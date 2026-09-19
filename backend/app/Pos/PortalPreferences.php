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
        'invoice_page_length' => ['10','15','25','50','100'],
        'inventory_page_length' => ['10','15','25','50','100'],
        'claims_page_length' => ['10','15','25','50','100'],
        'invoice_search_category' => ['all','invoice_id','customer_name','contact_number','item','imei','invoice_date','total'],
        'inventory_search_category' => ['all','product_name','sku','category','brand','model','variant','color','condition','pta_status','carrier_lock','mdm_status','purchase_price','sale_price','in_stock','sold','imei','warranty'],
        'warranty_search_category' => ['all','invoice_id','customer_name','customer_cnic','contact_number','product','imei'],
        'claims_search_category' => ['all','claim','invoice','customer','contact','product','imei','status','assigned'],
    ];
    private const DEFAULTS = [
        'invoice_page_length' => '15', 'inventory_page_length' => '10', 'claims_page_length' => '15',
        'invoice_search_category' => 'all', 'inventory_search_category' => 'all',
        'warranty_search_category' => 'all', 'claims_search_category' => 'all',
        'auto_focus_search' => true, 'remember_search' => false,
    ];
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
            if (is_bool($fallback)) {
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
        DB::transaction(function () use ($actor, $input) {
            $this->authorize($actor->fresh());
            foreach (self::DEFAULTS as $key => $default) {
                DB::table('pos_settings')->updateOrInsert(['key' => 'portal.'.$key], [
                    'value' => is_bool($default) ? ($input[$key] ? '1' : '0') : $input[$key],
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
}
