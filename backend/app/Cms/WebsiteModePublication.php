<?php

namespace App\Cms;

use App\Addendum\WebsiteCapabilities;
use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Infrastructure\VersionedCache;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WebsiteModePublication
{
    private const MODES = ['digital_only', 'hybrid', 'commerce_only'];

    private const ROUTES = [
        'common' => ['home', 'about', 'contact', 'policies', 'software'],
        'digital' => ['services', 'case-studies', 'knowledge', 'enquiry', 'consultation'],
        'commerce' => ['products', 'categories', 'compare', 'cart', 'checkout'],
    ];

    private const CTAS = [
        'digital' => ['service_enquiry', 'consultation'],
        'commerce' => ['browse_products', 'add_to_cart', 'checkout'],
    ];

    public function saveDraft(IdentityAccount $actor, string $mode): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.settings.manage'), 403);
        $snapshot = $this->snapshotFor($mode);

        return DB::transaction(function () use ($admin, $snapshot) {
            $version = (int) DB::table('site_configuration_revisions')
                ->where('domain', 'website.mode')->lockForUpdate()->max('version') + 1;
            $id = DB::table('site_configuration_revisions')->insertGetId([
                'domain' => 'website.mode', 'version' => $version, 'state' => 'draft',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_by_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_mode_draft_saved', 'site_configuration_revision:'.$id);

            return $this->revisionPayload($id);
        });
    }

    public function preview(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.mode.preview'), 403);
        $revision = $this->modeRevision($revisionId);
        $proposed = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $current = app(WebsiteCapabilities::class)->snapshot();
        $currentPlan = $current['mode'] ? $this->publishedSnapshot() : $this->snapshotFor(null);
        $fromScopes = $currentPlan['content_scopes'] ?? [];
        $toScopes = $proposed['content_scopes'];
        $pageImpact = $this->pageImpact($fromScopes, $toScopes);
        $navigationImpact = $this->navigationImpact($fromScopes, $toScopes);

        return [
            'revision_id' => $revision->id, 'revision_version' => (int) $revision->version,
            'from_mode' => $current['mode'], 'to_mode' => $proposed['mode'],
            'capabilities' => $this->arrayDiff($currentPlan['capabilities'] ?? [], $proposed['capabilities']),
            'routes' => $this->arrayDiff($currentPlan['routes'] ?? [], $proposed['routes']),
            'api_operations' => $this->arrayDiff($currentPlan['api_operations'] ?? [], $proposed['api_operations']),
            'ctas' => $this->arrayDiff($currentPlan['ctas'] ?? [], $proposed['ctas']),
            'content_scopes' => ['from' => $fromScopes, 'to' => $toScopes],
            'pages' => $pageImpact, 'navigation' => $navigationImpact,
            'seo_sitemap' => $proposed['seo_sitemap'],
            'historical_access' => $proposed['historical_access'],
            'data_deletion' => false, 'affected_cache_domains' => $this->affectedDomains($currentPlan, $proposed),
            'snapshot_sha256' => $this->hash($proposed),
        ];
    }

    public function publish(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.mode.publish'), 403);

        return DB::transaction(fn () => $this->publishLocked($admin, $revisionId));
    }

    public function rollback(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.mode.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $source = $this->modeRevision($revisionId, true);
            abort_unless(in_array($source->state, ['published', 'superseded'], true), 409, 'Rollback requires published mode history.');
            $version = (int) DB::table('site_configuration_revisions')
                ->where('domain', 'website.mode')->lockForUpdate()->max('version') + 1;
            $newId = DB::table('site_configuration_revisions')->insertGetId([
                'domain' => 'website.mode', 'version' => $version, 'state' => 'draft',
                'snapshot' => $source->snapshot, 'created_by_admin_id' => $admin->id,
                'restored_from_revision_id' => $source->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $published = $this->publishLocked($admin, $newId);
            IdentityAudit::record('admin', $admin->id, 'website_mode_rolled_back', 'site_configuration_revision:'.$newId);

            return $published;
        });
    }

    public function publicProfile(): array
    {
        return app(VersionedCache::class)->remember('website.mode', 'public-profile', function () {
            $capabilities = app(WebsiteCapabilities::class)->snapshot();
            if ($capabilities['mode'] === null) {
                return $this->snapshotFor(null) + ['version' => 0, 'published_at' => null];
            }
            $profile = DB::table('website_operating_profiles')->where('id', 1)->firstOrFail();
            $snapshot = $this->publishedSnapshot();

            return $snapshot + ['version' => (int) $profile->version, 'published_at' => $profile->published_at];
        });
    }

    private function publishLocked(Admin $admin, int $revisionId): array
    {
        $revision = $this->modeRevision($revisionId, true);
        abort_unless($revision->state === 'draft', 409, 'Only a draft Website mode revision can be published.');
        $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $canonical = $this->snapshotFor($snapshot['mode'] ?? null);
        abort_unless(hash_equals($this->hash($snapshot), $this->hash($canonical)), 409, 'Website mode draft no longer matches the publication contract.');
        $profile = DB::table('website_operating_profiles')->where('id', 1)->lockForUpdate()->first();
        $previous = $profile ? $this->publishedSnapshot() : $this->snapshotFor(null);
        DB::table('site_configuration_revisions')->where('domain', 'website.mode')->where('state', 'published')
            ->update(['state' => 'superseded', 'updated_at' => now()]);
        DB::table('site_configuration_revisions')->where('id', $revisionId)->update([
            'state' => 'published', 'published_by_admin_id' => $admin->id,
            'published_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => $snapshot['mode'], 'version' => $revision->version,
            'revision_id' => $revisionId, 'published_at' => now(),
        ]);
        $domains = $this->affectedDomains($previous, $snapshot);
        foreach ($domains as $domain) {
            $this->bump($domain);
        }
        DB::table('domain_events')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'aggregate_type' => 'website_mode', 'aggregate_id' => 'singleton',
            'aggregate_version' => (int) $revision->version, 'event_type' => 'website_mode_revalidated',
            'payload' => json_encode(['mode' => $snapshot['mode'], 'affected_cache_domains' => $domains], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'operation_key' => 'website-mode-revalidate:'.$revisionId, 'completed_at' => now(), 'created_at' => now(),
        ]);
        IdentityAudit::record('admin', $admin->id, 'website_mode_published', 'site_configuration_revision:'.$revisionId);

        return [
            'revision_id' => $revisionId, 'version' => (int) $revision->version,
            'mode' => $snapshot['mode'], 'affected_cache_domains' => $domains,
            'profile' => $snapshot, 'data_deleted' => false,
        ];
    }

    private function snapshotFor(?string $mode): array
    {
        if ($mode !== null && ! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Unknown Website operating mode.']);
        }
        $commerce = in_array($mode, ['hybrid', 'commerce_only'], true);
        $digital = in_array($mode, ['hybrid', 'digital_only'], true);
        $scopes = $mode === null ? [] : array_values(array_filter([
            'common', $digital ? 'digital' : null, $commerce ? 'commerce' : null, $mode,
        ]));
        $routes = $mode === null ? [] : [...self::ROUTES['common'], ...($digital ? self::ROUTES['digital'] : []), ...($commerce ? self::ROUTES['commerce'] : [])];
        $excludedRoutes = $mode === null
            ? [...self::ROUTES['common'], ...self::ROUTES['digital'], ...self::ROUTES['commerce']]
            : [...($digital ? [] : self::ROUTES['digital']), ...($commerce ? [] : self::ROUTES['commerce'])];
        $operations = [];
        foreach (WebsiteCapabilities::PUBLIC_OPERATIONS as $operation => $capability) {
            if (($capability === 'digital' && $digital) || ($capability === 'commerce' && $commerce)) {
                $operations[] = $operation;
            }
        }
        $ctas = [...($digital ? self::CTAS['digital'] : []), ...($commerce ? self::CTAS['commerce'] : [])];
        $historical = [];
        foreach (WebsiteCapabilities::HISTORICAL_RESOURCES as $resource) {
            $historical[$resource] = ['allowed' => true, 'authenticated_only' => true, 'indexable' => false];
        }

        return [
            'contract' => 'website-mode.v1', 'mode' => $mode,
            'capabilities' => ['digital' => $digital, 'commerce' => $commerce],
            'content_scopes' => $scopes, 'copy_variant' => $mode,
            'routes' => $routes, 'api_operations' => $operations, 'ctas' => $ctas,
            'seo_sitemap' => ['discoverable_routes' => $routes, 'excluded_routes' => $excludedRoutes,
                'historical_routes_indexable' => false, 'inactive_capabilities_indexable' => false],
            'historical_access' => $historical,
        ];
    }

    private function pageImpact(array $fromScopes, array $toScopes): array
    {
        $added = [];
        $removed = [];
        $pages = DB::table('site_managed_pages')->where('publish_state', 'published')->whereNotNull('current_revision_id')
            ->orderBy('slug')->get(['slug', 'capability_scope']);
        foreach ($pages as $page) {
            $from = in_array($page->capability_scope, $fromScopes, true);
            $to = in_array($page->capability_scope, $toScopes, true);
            if (! $from && $to) {
                $added[] = $page->slug;
            } elseif ($from && ! $to) {
                $removed[] = $page->slug;
            }
        }

        return ['becomes_visible' => $added, 'becomes_hidden' => $removed];
    }

    private function navigationImpact(array $fromScopes, array $toScopes): array
    {
        $added = [];
        $removed = [];
        $items = DB::table('site_navigation_items')->where('is_visible', true)->where('is_enabled', true)
            ->orderBy('key')->get(['key', 'capability_scope']);
        foreach ($items as $item) {
            $from = in_array($item->capability_scope, $fromScopes, true);
            $to = in_array($item->capability_scope, $toScopes, true);
            if (! $from && $to) {
                $added[] = $item->key;
            } elseif ($from && ! $to) {
                $removed[] = $item->key;
            }
        }

        return ['becomes_visible' => $added, 'becomes_hidden' => $removed];
    }

    private function affectedDomains(array $from, array $to): array
    {
        $domains = ['website.mode'];
        if (($from['content_scopes'] ?? []) !== ($to['content_scopes'] ?? []) || ($from['routes'] ?? []) !== ($to['routes'] ?? [])) {
            $domains[] = 'cms.pages';
            $domains[] = 'cms.presentation';
        }
        if (($from['capabilities']['commerce'] ?? false) !== ($to['capabilities']['commerce'] ?? false)) {
            $domains[] = 'catalogue';
        }

        return array_values(array_unique($domains));
    }

    private function arrayDiff(array $from, array $to): array
    {
        if (array_is_list($from) && array_is_list($to)) {
            return [
                'enabled' => array_values(array_diff($to, $from)),
                'disabled' => array_values(array_diff($from, $to)),
                'from' => $from, 'to' => $to,
            ];
        }
        $changed = [];
        foreach (array_unique([...array_keys($from), ...array_keys($to)]) as $key) {
            if (($from[$key] ?? null) !== ($to[$key] ?? null)) {
                $changed[$key] = ['from' => $from[$key] ?? null, 'to' => $to[$key] ?? null];
            }
        }

        return ['changed' => $changed, 'from' => $from, 'to' => $to];
    }

    private function publishedSnapshot(): array
    {
        $profile = DB::table('website_operating_profiles')->where('id', 1)->firstOrFail();
        $revision = DB::table('site_configuration_revisions')->where('id', $profile->revision_id)->firstOrFail();
        abort_unless($revision->domain === 'website.mode' && $revision->state === 'published'
            && (int) $revision->version === (int) $profile->version, 409, 'Published Website mode pointer is inconsistent.');

        return json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
    }

    private function modeRevision(int $revisionId, bool $lock = false): object
    {
        return DB::table('site_configuration_revisions')->where('id', $revisionId)->where('domain', 'website.mode')
            ->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    private function revisionPayload(int $revisionId): array
    {
        $revision = $this->modeRevision($revisionId);
        $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $revision->id, 'version' => (int) $revision->version, 'state' => $revision->state,
            'mode' => $snapshot['mode'], 'snapshot' => $snapshot, 'snapshot_sha256' => $this->hash($snapshot),
            'restored_from_revision_id' => $revision->restored_from_revision_id ? (int) $revision->restored_from_revision_id : null,
        ];
    }

    private function bump(string $domain): void
    {
        DB::table('publication_versions')->insertOrIgnore(['domain' => $domain, 'version' => 0, 'updated_at' => now()]);
        DB::table('publication_versions')->where('domain', $domain)->increment('version', 1, ['updated_at' => now()]);
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    private function admin(IdentityAccount $actor): Admin
    {
        abort_unless($actor instanceof Admin, 403);

        return $actor;
    }
}
