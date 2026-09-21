<?php

namespace App\Pos;

use App\Identity\IdentityAudit;
use App\Identity\OutletLifecycleAdministration;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DashboardReportPreferences
{
    private const KEY = 'portal.dashboard_report_sections';

    private const SECTIONS = [
        'sales' => ['label' => 'Sales metrics', 'order' => 10],
        'payments' => ['label' => 'Payment mix', 'order' => 20],
        'categories' => ['label' => 'Category performance and inventory', 'order' => 30],
        'activity' => ['label' => 'Operational activity', 'order' => 40],
    ];

    public function canManage(Admin $actor): bool
    {
        return app(OutletLifecycleAdministration::class)->canManage($actor)
            && $actor->hasPermission('config.dashboard-reports.manage');
    }

    public function catalogue(Admin $actor): array
    {
        abort_unless($this->canManage($actor), 403);

        return ['sections' => $this->current()];
    }

    public function current(): array
    {
        $raw = DB::table('pos_settings')->where('key', self::KEY)->value('value');
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        $stored = is_array($stored) ? $stored : [];

        return collect(self::SECTIONS)->map(function (array $definition, string $key) use ($stored) {
            $override = is_array($stored[$key] ?? null) ? $stored[$key] : [];

            return ['key' => $key, 'label' => $definition['label'],
                'visible' => ($override['visible'] ?? true) === true,
                'order' => is_int($override['order'] ?? null) ? $override['order'] : $definition['order']];
        })->sortBy(fn (array $section) => [$section['order'], $section['key']])->values()->all();
    }

    public function update(Admin $actor, array $input): array
    {
        abort_unless($this->canManage($actor), 403);
        if (array_keys($input) !== ['sections'] || ! is_array($input['sections'])) {
            throw ValidationException::withMessages(['sections' => 'Submit the complete dashboard/report section set.']);
        }
        $submitted = collect($input['sections'])->keyBy('key');
        if ($submitted->keys()->sort()->values()->all() !== collect(array_keys(self::SECTIONS))->sort()->values()->all()) {
            throw ValidationException::withMessages(['sections' => 'Dashboard/report sections are incomplete or unsupported.']);
        }
        $normalized = [];
        foreach (self::SECTIONS as $key => $definition) {
            $row = $submitted[$key];
            if (! is_array($row) || array_diff(array_keys($row), ['key', 'visible', 'order'])
                || ! is_bool($row['visible'] ?? null) || ! is_int($row['order'] ?? null)
                || $row['order'] < 10 || $row['order'] > 999) {
                throw ValidationException::withMessages(["sections.{$key}" => 'Section visibility or order is invalid.']);
            }
            $normalized[$key] = ['visible' => $row['visible'], 'order' => $row['order']];
        }
        DB::transaction(function () use ($actor, $normalized) {
            abort_unless($this->canManage($actor->fresh()), 403);
            DB::table('pos_settings')->updateOrInsert(['key' => self::KEY], [
                'value' => json_encode($normalized, JSON_THROW_ON_ERROR), 'group' => 'portal',
                'label' => 'dashboard report sections', 'input_type' => 'structured',
                'sort_order' => 260, 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $actor->id, 'pos_dashboard_report_preferences_updated', 'portal:dashboard-reports');
        });

        return $this->current();
    }
}
