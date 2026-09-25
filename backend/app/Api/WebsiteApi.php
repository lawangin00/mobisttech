<?php

namespace App\Api;

use App\Addendum\WebsiteCapabilities;
use App\Business\BusinessProfile;
use App\Cms\CataloguePresentation;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use App\Digital\DigitalServiceLeads;
use App\Infrastructure\VersionedCache;
use App\Inventory\StockLedger;
use App\Migration\SourceRow;
use App\Models\CustomerAccount;
use App\Models\Product;
use App\Models\StockUnit;
use App\Support\ProductVariantKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class WebsiteApi
{
    public function __construct(
        private BusinessProfile $business,
        private WebsiteCapabilities $capabilities,
        private WebsiteModePublication $modes,
        private WebsiteCms $cms,
        private DigitalServiceLeads $digital,
        private VersionedCache $cache,
        private StockLedger $stock,
    ) {}

    public function profile(): array
    {
        $profile = $this->modes->publicProfile();
        $caps = $this->capabilities->snapshot();

        return [
            'mode' => $profile['mode'],
            'version' => (int) ($profile['version'] ?? 0),
            'published_at' => $profile['published_at'] ?? null,
            'capabilities' => $profile['capabilities'],
            'content_scopes' => $profile['content_scopes'],
            'copy_variant' => $profile['copy_variant'] ?? $profile['mode'],
            'routes' => $profile['routes'],
            'api_operations' => $profile['api_operations'],
            'ctas' => $profile['ctas'] ?? [],
            'seo_sitemap' => $profile['seo_sitemap'] ?? [],
            'historical_access' => $profile['historical_access'] ?? [],
            'cache_namespace' => $caps['cache_namespace'],
            'business' => $this->business->current(),
            'content' => $this->contentIndex(),
        ];
    }

    public function catalogue(array $input): array
    {
        $this->assertScope('commerce');
        $data = Validator::make($input, [
            'limit' => 'sometimes|integer|min:1|max:48',
            'after' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:50',
            'subcategory' => 'nullable|string|min:2|max:100|regex:/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/',
            'availability' => 'nullable|in:in_stock,out_of_stock',
            'q' => 'nullable|string|min:2|max:80',
            'sort' => 'sometimes|in:oldest,newest,price_asc,price_desc,name_asc,name_desc',
            'min_price' => 'nullable|numeric|min:0|max:999999999',
            'max_price' => 'nullable|numeric|min:0|max:999999999',
            'brand' => 'nullable|string|min:2|max:80',
            'model' => 'nullable|string|min:2|max:80',
            'condition' => ['nullable', 'string', Rule::in(array_keys(StockUnit::CONDITIONS))],
            'pta_status' => ['nullable', 'string', Rule::in(array_keys(StockUnit::PTA_STATUSES))],
            'ram_gb' => 'nullable|integer|min:1|max:2048',
            'storage_gb' => 'nullable|integer|min:1|max:8192',
        ])->validate();
        $limit = (int) ($data['limit'] ?? 12);
        $sort = $data['sort'] ?? 'oldest';
        $after = $sort === 'oldest' ? $this->decodeCursor($data['after'] ?? null)
            : $this->decodeCatalogueSortCursor($data['after'] ?? null, $sort);
        $category = isset($data['category']) ? trim($data['category']) : null;
        $subcategory = $data['subcategory'] ?? null;
        $availability = $data['availability'] ?? null;
        $query = isset($data['q']) ? trim($data['q']) : null;
        $minPrice = isset($data['min_price']) ? (float) $data['min_price'] : null;
        $maxPrice = isset($data['max_price']) ? (float) $data['max_price'] : null;
        $brand = isset($data['brand']) ? trim($data['brand']) : null;
        $model = isset($data['model']) ? trim($data['model']) : null;
        $condition = $data['condition'] ?? null;
        $ptaStatus = $data['pta_status'] ?? null;
        $ram = $data['ram_gb'] ?? null;
        $storage = $data['storage_gb'] ?? null;
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            throw ValidationException::withMessages(['max_price' => 'Max price must not be lower than min price.']);
        }
        $resource = json_encode(compact('limit', 'after', 'category', 'subcategory', 'availability', 'query', 'sort', 'minPrice', 'maxPrice', 'brand', 'model', 'condition', 'ptaStatus', 'ram', 'storage'), JSON_THROW_ON_ERROR);

        return $this->cache->remember('catalogue', 'api:v1:products:'.$resource, function () use ($limit, $after, $category, $subcategory, $availability, $query, $sort, $minPrice, $maxPrice, $brand, $model, $condition, $ptaStatus, $ram, $storage) {
            return DB::transaction(function () use ($limit, $after, $category, $subcategory, $availability, $query, $sort, $minPrice, $maxPrice, $brand, $model, $condition, $ptaStatus, $ram, $storage) {
                $rows = DB::table('product_listings as l')->join('products as p', 'p.id', '=', 'l.product_id')
                    ->where('l.is_online', true)->where('p.isDeleted', false)->whereNull('p.archived_at')
                    ->when($category, fn ($q) => $q->where('p.category', $category))
                    ->when($subcategory, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')
                        ->from('pos_master_data_options as sc')->whereColumn('sc.id', 'p.subcategory_master_data_id')
                        ->where('sc.list_key', 'product_subcategory')->where('sc.code', $subcategory)))
                    ->when($query, fn ($q) => $q->where('p.name', 'like', '%'.$this->escapeLike($query).'%'))
                    ->when($minPrice !== null, fn ($q) => $q->where('p.sale_price', '>=', $minPrice))
                    ->when($maxPrice !== null, fn ($q) => $q->where('p.sale_price', '<=', $maxPrice))
                    ->when($brand, fn ($q) => $q->where(fn ($matching) => $matching
                        ->whereExists(fn ($managed) => $managed->selectRaw('1')->from('pos_master_data_options as b')
                            ->whereColumn('b.id', 'p.brand_master_data_id')->where('b.label', $brand))
                        ->orWhere(fn ($legacy) => $legacy->whereNull('p.brand_master_data_id')->where('p.brand', $brand))))
                    ->when($model, fn ($q) => $q->where('p.model', 'like', '%'.$this->escapeLike($model).'%'))
                    ->when($ram !== null, fn ($q) => $q->where('p.ram_gb', $ram))
                    ->when($storage !== null, fn ($q) => $q->where('p.storage_gb', $storage))
                    ->when($condition !== null || $ptaStatus !== null, fn ($q) => $q->whereExists(
                        fn ($unit) => $unit->selectRaw('1')->from('stock_units as su')
                            ->whereColumn('su.product_id', 'p.id')->where('su.status', 'in_stock')
                            ->whereNotExists(fn ($hold) => $hold->selectRaw('1')->from('reservation_allocations as ra')
                                ->whereColumn('ra.stock_unit_id', 'su.id')->whereNull('ra.released_at'))
                            ->whereNotExists(fn ($custody) => $custody->selectRaw('1')->from('inventory_custody_holds as ch')
                                ->whereColumn('ch.stock_unit_id', 'su.id')->whereNull('ch.released_at'))
                            ->when($condition !== null, fn ($u) => $u->where('su.condition', $condition))
                            ->when($ptaStatus !== null, fn ($u) => $u->where('su.pta_status', $ptaStatus))));
                $column = str_starts_with($sort, 'price_') ? 'p.sale_price' : 'p.name';
                $descending = in_array($sort, ['newest', 'price_desc', 'name_desc'], true);
                if ($after !== null && $sort !== 'oldest' && $sort !== 'newest') {
                    $anchor = DB::table('product_listings as l')->join('products as p', 'p.id', '=', 'l.product_id')
                        ->where('l.id', $after)->value($column);
                    if ($anchor === null) {
                        throw ValidationException::withMessages(['after' => 'Invalid pagination cursor.']);
                    }
                    $rows->where(fn ($q) => $q->where($column, $descending ? '<' : '>', $anchor)
                        ->orWhere(fn ($tie) => $tie->where($column, $anchor)->where('l.id', '>', $after)));
                } elseif ($after !== null) {
                    $rows->where('l.id', $sort === 'newest' ? '<' : '>', $after);
                }
                if ($sort === 'oldest' || $sort === 'newest') {
                    $rows->orderBy('l.id', $sort === 'newest' ? 'desc' : 'asc');
                } else {
                    $rows->orderBy($column, $descending ? 'desc' : 'asc')->orderBy('l.id');
                }
                $columns = ['l.id as listing_id', 'l.public_id as listing_public_id', 'l.slug', 'l.image_url', 'l.warranty_summary',
                    'p.id as product_id', 'p.public_id as product_public_id', 'p.name', 'p.brand', 'p.model',
                    'p.category', 'p.sale_price', 'p.warranty_type', 'p.track_imei'];
                if ($availability !== null) {
                    // Filter by the authoritative locked stock snapshot BEFORE choosing a page boundary.
                    // Scan ordered candidates in bounded batches so zero-stock rows cannot break cursors.
                    $items = [];
                    $last = null;
                    $offset = 0;
                    $hasMore = false;
                    do {
                        $batch = (clone $rows)->offset($offset)->limit(48)->get($columns);
                        foreach ($batch as $candidate) {
                            $public = $this->productProjection($candidate);
                            if ($public['availability']['in_stock'] !== ($availability === 'in_stock')) {
                                continue;
                            }
                            if (count($items) === $limit) {
                                $hasMore = true;
                                break 2;
                            }
                            $items[] = $public;
                            $last = $candidate;
                        }
                        $offset += $batch->count();
                    } while ($batch->count() === 48);

                    return ['items' => $items, 'page' => ['limit' => $limit, 'has_more' => $hasMore,
                        'next_cursor' => $hasMore && $last ? ($sort === 'oldest' ? $this->encodeCursor((int) $last->listing_id)
                            : $this->encodeCatalogueSortCursor($sort, (int) $last->listing_id)) : null]];
                }
                $rows = $rows->limit($limit + 1)->get($columns);
                $hasMore = $rows->count() > $limit;
                $rows = $rows->take($limit);
                $items = $rows->map(fn ($row) => $this->productProjection($row))->all();
                $last = $rows->last();

                return ['items' => $items, 'page' => ['limit' => $limit, 'has_more' => $hasMore,
                    'next_cursor' => $hasMore && $last ? ($sort === 'oldest' ? $this->encodeCursor((int) $last->listing_id)
                        : $this->encodeCatalogueSortCursor($sort, (int) $last->listing_id)) : null]];
            }, 2);
        });
    }

    public function product(string $slug): array
    {
        $this->assertScope('commerce');
        $slug = $this->slug($slug);

        $payload = $this->cache->remember('catalogue', 'api:v1:product:'.$slug, function () use ($slug) {
            return DB::transaction(function () use ($slug) {
                $row = DB::table('product_listings as l')->join('products as p', 'p.id', '=', 'l.product_id')
                    ->where('l.slug', $slug)->where('l.is_online', true)->where('p.isDeleted', false)->whereNull('p.archived_at')
                    ->select('l.id as listing_id', 'l.public_id as listing_public_id', 'l.slug', 'l.image_url', 'l.description',
                        'l.warranty_summary', 'p.id as product_id', 'p.public_id as product_public_id', 'p.name', 'p.brand',
                        'p.model', 'p.category', 'p.sale_price', 'p.warranty_type', 'p.track_imei')->firstOrFail();
                $payload = $this->productProjection($row);
                $payload['description'] = $row->description;
                $payload['warranty_summary'] = $row->warranty_summary;
                $product = Product::findOrFail($row->product_id);
                $payload['variants'] = $this->publicVariants($product);
                // Only public POS-managed device configuration; no private unit identity or cost.
                $payload['device'] = ['ram_gb' => $product->ram_gb === null ? null : (int) $product->ram_gb,
                    'storage_gb' => $product->storage_gb === null ? null : (int) $product->storage_gb,
                    'sim' => $product->simDisplay()];

                return $payload;
            }, 2);
        });
        $listingId = DB::table('product_listings')->where('slug', $slug)->where('is_online', true)->value('id') ?? abort(404);
        $payload['reviews'] = $this->approvedReviews((int) $listingId, 10);

        return $payload;
    }

    public function categories(): array
    {
        $this->assertScope('commerce');

        return $this->cache->remember('catalogue', 'api:v1:categories', fn () => DB::table('product_listings as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')->where('l.is_online', true)
            ->where('p.isDeleted', false)->whereNull('p.archived_at')->groupBy('p.category')->orderBy('p.category')
            ->selectRaw('p.category as code, COUNT(*) as products')->get()
            ->map(fn ($row) => ['code' => $row->code, 'label' => Product::CATEGORY_LABELS[$row->code] ?? $row->code,
                'products' => (int) $row->products])->all());
    }

    public function contentIndex(): array
    {
        $pages = DB::table('site_managed_pages as page')
            ->join('site_page_revisions as revision', 'revision.id', '=', 'page.current_revision_id')
            ->where('page.publish_state', 'published')->where('revision.state', 'published')->where('page.is_indexable', true)
            ->orderBy('page.title')->get([
                'page.slug', 'page.title', 'page.content_purpose', 'page.capability_scope',
                'page.show_in_navigation', 'revision.snapshot',
            ])
            ->filter(function ($row) {
                if (! $this->capabilities->allowsScope((string) $row->capability_scope)) {
                    return false;
                }
                if ($row->content_purpose !== 'digital_testimonial') {
                    return true;
                }
                try {
                    $this->cms->publicPage((string) $row->slug);

                    return true;
                } catch (HttpExceptionInterface) {
                    return false;
                }
            })
            ->map(function ($row) {
                $entry = [
                    'slug' => $row->slug, 'title' => $row->title, 'purpose' => $row->content_purpose,
                    'scope' => $row->capability_scope, 'show_in_navigation' => (bool) $row->show_in_navigation,
                ];
                if (in_array($row->content_purpose, ['faq', 'insight', 'guide'], true)) {
                    $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
                    $structured = is_array($snapshot['structured_content'] ?? null) ? $snapshot['structured_content'] : [];
                    $entry['category'] = is_string($structured['category'] ?? null) ? $structured['category'] : null;
                    $entry['tags'] = array_values(array_filter($structured['tags'] ?? [], 'is_string'));
                    $entry['service_slugs'] = array_values(array_filter($snapshot['service_slugs'] ?? [], 'is_string'));
                }

                return $entry;
            })->values()->all();

        $software = DB::table('software_products')->where('lifecycle_state', 'published')->whereNotNull('current_revision_id')
            ->orderBy('name')->get(['slug', 'name'])->map(function ($row) {
                $published = $this->cms->publicSoftware($row->slug);

                return ['slug' => $row->slug, 'name' => $row->name, 'routes' => $published['routes'],
                    'sitemap' => ($published['snapshot']['seo']['sitemap'] ?? true) === true];
            })->all();

        $navigationRows = DB::table('site_navigation_items')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'parent_id', 'key', 'label', 'is_visible', 'is_enabled', 'destination_type', 'destination_key',
                'destination_payload', 'target_behavior', 'capability_scope']);
        $byId = $navigationRows->keyBy('id');
        $available = $navigationRows->filter(fn ($row) => $row->is_visible && $row->is_enabled
            && $this->capabilities->allowsScope((string) $row->capability_scope))->keyBy('id');
        // A child is public only when every ancestor is public; cycles and missing parents fail closed.
        $allowed = [];
        for ($pass = 0; $pass < $available->count(); $pass++) {
            foreach ($available as $row) {
                if ($row->parent_id === null || isset($allowed[$row->parent_id])) {
                    $allowed[$row->id] = true;
                }
            }
        }
        $navigation = $navigationRows->filter(fn ($row) => isset($allowed[$row->id]))
            ->map(fn ($row) => [
                'key' => $row->key, 'parent_key' => $row->parent_id ? ($byId[$row->parent_id]->key ?? null) : null,
                'label' => $row->label, 'destination_type' => $row->destination_type,
                'destination_key' => $row->destination_key,
                'destination_payload' => $row->destination_payload ? json_decode($row->destination_payload, true, flags: JSON_THROW_ON_ERROR) : null,
                'target_behavior' => $row->target_behavior, 'scope' => $row->capability_scope,
            ])->values()->all();

        $homeJson = DB::table('site_settings')->where('key', 'cms.presentation.homepage')->value('value');
        $headerFooterJson = DB::table('site_settings')->where('key', 'cms.presentation.header_footer')->value('value');
        $homepage = $homeJson ? json_decode($homeJson, true, flags: JSON_THROW_ON_ERROR) : [];
        $headerFooter = $headerFooterJson ? json_decode($headerFooterJson, true, flags: JSON_THROW_ON_ERROR) : [];
        $themeJson = DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value');
        $theme = $themeJson ? json_decode($themeJson, true, flags: JSON_THROW_ON_ERROR) : [];
        $brandingJson = DB::table('site_settings')->where('key', 'cms.presentation.branding')->value('value');
        $branding = $brandingJson ? json_decode($brandingJson, true, flags: JSON_THROW_ON_ERROR) : [];
        // Public payload only exposes published, active, scoped private image IDs. No paths or drafts.
        $publicBranding = [];
        foreach (['main_logo', 'wordmark', 'header_logo', 'footer_logo', 'square_icon', 'favicon', 'social_image'] as $role) {
            $id = $branding[$role] ?? null;
            $publicBranding[$role] = is_int($id) && $id > 0 && DB::table('site_media_assets')->where('id', $id)
                ->where('status', 'active')->where('disk', 'local')->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
                ->where('path', 'regexp', '^cms/[0-9a-f-]+\\.(png|jpe?g|webp)$')->exists() ? $id : null;
        }
        $seoJson = DB::table('site_settings')->where('key', 'cms.presentation.seo')->value('value');
        $seo = $seoJson ? json_decode($seoJson, true, flags: JSON_THROW_ON_ERROR) : [];
        $promotionJson = DB::table('site_settings')->where('key', 'cms.presentation.promotion')->value('value');
        $promotion = $promotionJson ? json_decode($promotionJson, true, flags: JSON_THROW_ON_ERROR) : [];
        $announcement = is_array($promotion['announcement'] ?? null) ? $promotion['announcement'] : null;
        $bannerRows = is_array($promotion['banners'] ?? null) ? $promotion['banners'] : [];
        $publicPromotion = [
            'announcement' => $announcement && $this->capabilities->allowsScope((string) ($announcement['scope'] ?? 'common')) ? $announcement : null,
            'banners' => array_values(array_filter($bannerRows, fn ($banner) => is_array($banner)
                && $this->capabilities->allowsScope((string) ($banner['scope'] ?? 'common')))),
        ];

        return [
            'pages' => $pages,
            'policies' => array_map(fn ($policy) => array_intersect_key($policy, array_flip([
                'type', 'slug', 'title', 'footer_destination', 'effective_date', 'version',
            ])), $this->cms->publicPolicies()),
            'software' => $software,
            'navigation' => $navigation,
            'promotion' => $publicPromotion,
            'seo' => $seo,
            'homepage' => $homepage,
            'header_footer' => $headerFooter,
            'theme' => $theme,
            'branding' => $publicBranding,
            'catalogue' => app(CataloguePresentation::class)->publicValues(),
        ];
    }

    public function page(string $slug): array
    {
        $payload = $this->cache->remember('cms.pages', 'api:v1:page:'.$slug, fn () => $this->cms->publicPage($slug));
        $scope = (string) ($payload['snapshot']['capability_scope'] ?? 'common');
        abort_unless($this->capabilities->allowsScope($scope), 404);
        if (($payload['snapshot']['content_purpose'] ?? null) === 'case_study') {
            $rows = DB::table('site_managed_pages as page')
                ->join('site_page_revisions as revision', 'revision.id', '=', 'page.current_revision_id')
                ->where('page.publish_state', 'published')->where('revision.state', 'published')
                ->where('page.content_purpose', 'digital_testimonial')->orderBy('page.id')->limit(201)
                ->get(['page.slug', 'page.title', 'revision.snapshot']);
            abort_if($rows->count() > 200, 503, 'Published testimonial linkage exceeds the API payload budget.');
            $related = [];
            foreach ($rows as $row) {
                $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
                $structured = is_array($snapshot['structured_content'] ?? null) ? $snapshot['structured_content'] : [];
                if (($structured['consent_confirmed'] ?? false) !== true
                    || ($structured['moderation_state'] ?? null) !== 'approved'
                    || ($structured['display_enabled'] ?? false) !== true
                    || ! in_array($slug, $structured['case_study_slugs'] ?? [], true)
                    || ! $this->capabilities->allowsScope((string) ($snapshot['capability_scope'] ?? 'common'))) {
                    continue;
                }
                $related[] = [
                    'slug' => $row->slug, 'title' => $row->title,
                    'display_order' => (int) ($structured['display_order'] ?? 100),
                ];
            }
            usort($related, fn (array $left, array $right) => [$left['display_order'], $left['slug']] <=> [$right['display_order'], $right['slug']]);
            $payload['related_testimonials'] = array_map(
                fn (array $row) => ['slug' => $row['slug'], 'title' => $row['title']],
                array_slice($related, 0, 8)
            );
        }

        return $payload;
    }

    public function policies(): array
    {
        $this->assertScope('common');

        return $this->cache->remember('cms.policies', 'api:v1:policies', fn () => $this->cms->publicPolicies());
    }

    public function software(string $slug): array
    {
        $this->assertScope('common');
        $public = $this->softwarePublic($slug);
        $snapshot = $public['snapshot'];

        return ['public_id' => $public['public_id'], 'slug' => $public['slug'], 'revision' => $public['revision'],
            'published_at' => $public['published_at'], 'current_version' => $public['current_version'],
            'overview' => array_intersect_key($snapshot, array_flip([
                'name', 'summary', 'overview', 'features', 'platforms', 'system_requirements', 'logo_media_id',
                'icon_media_id', 'hero_media_id', 'screenshot_media_ids', 'demo_media_id', 'limitations', 'support', 'cta', 'seo',
            ])),
            'latest_releases' => array_slice($public['releases'], 0, 10),
            'routes' => $public['routes'], 'sha256' => $public['sha256']];
    }

    public function softwareSection(string $slug, string $section): array
    {
        $this->assertScope('common');
        abort_unless(in_array($section, ['privacy', 'terms', 'faq', 'releases'], true), 404);
        $public = $this->softwarePublic($slug);
        if ($section === 'releases') {
            return ['slug' => $public['slug'], 'current_version' => $public['current_version'],
                'items' => array_slice($public['releases'], 0, 50), 'truncated' => count($public['releases']) > 50];
        }

        return ['slug' => $public['slug'], 'revision' => $public['revision'], $section => $public['snapshot'][$section] ?? ($section === 'faq' ? [] : '')];
    }

    public function consultation(): array
    {
        $this->assertScope('digital');
        $row = DB::table('digital_consultation_settings')->where('id', 1)->first();
        if (! $row) {
            return ['enabled' => false, 'timezone' => null, 'weekly_availability' => []];
        }

        return [
            'enabled' => (bool) $row->enabled,
            'timezone' => $row->timezone,
            'weekly_availability' => json_decode($row->weekly_availability ?: '[]', true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    public function services(): array
    {
        $this->assertScope('digital');
        $items = $this->digital->catalogue();
        abort_if(count($items) > 100, 503, 'Published service catalogue exceeds the API payload budget.');

        // Join only active published CMS revisions to the already bounded Digital Service
        // catalogue. Never read a draft page's mutable display columns or expose admin JSON.
        $related = [];
        if ($items !== []) {
            $rows = DB::table('site_page_service_links as link')
                ->join('digital_services as service', 'service.id', '=', 'link.digital_service_id')
                ->join('site_managed_pages as page', 'page.id', '=', 'link.site_managed_page_id')
                ->join('site_page_revisions as revision', 'revision.id', '=', 'page.current_revision_id')
                ->whereIn('service.slug', array_column($items, 'slug'))
                ->where('page.publish_state', 'published')->where('revision.state', 'published')
                ->whereIn('page.content_purpose', ['service_landing', 'case_study', 'digital_testimonial'])
                ->orderBy('page.id')->limit(801)
                ->get(['service.slug as service_slug', 'page.slug as public_slug', 'revision.snapshot']);
            abort_if($rows->count() > 800, 503, 'Published service content exceeds the bounded API payload.');
            foreach ($rows as $row) {
                $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
                $kind = $snapshot['content_purpose'] ?? null;
                if (! in_array($kind, ['service_landing', 'case_study', 'digital_testimonial'], true)
                    || ! $this->capabilities->allowsScope((string) ($snapshot['capability_scope'] ?? ''))
                    || ($snapshot['slug'] ?? null) !== $row->public_slug
                    || ($kind === 'digital_testimonial' && (
                        ($snapshot['structured_content']['consent_confirmed'] ?? false) !== true
                        || ($snapshot['structured_content']['moderation_state'] ?? null) !== 'approved'
                        || ($snapshot['structured_content']['display_enabled'] ?? false) !== true
                    ))) {
                    continue;
                }
                $related[$row->service_slug] ??= ['landing' => null, 'related_pages' => []];
                if ($kind === 'service_landing' && $related[$row->service_slug]['landing'] === null) {
                    $source = $snapshot['structured_content'] ?? [];
                    $source = is_array($source) ? $source : [];
                    $publicStructured = [];
                    foreach (['hero_heading', 'hero_body', 'problem', 'outcome'] as $field) {
                        $value = $source[$field] ?? null;
                        if (is_string($value) && mb_strlen($value) <= 2000) {
                            $publicStructured[$field] = $value;
                        }
                    }
                    foreach (['features', 'deliverables', 'process', 'technologies'] as $field) {
                        $values = $source[$field] ?? null;
                        if (is_array($values) && array_is_list($values)) {
                            $publicStructured[$field] = array_values(array_filter(array_slice($values, 0, 12),
                                fn ($value) => is_string($value) && mb_strlen($value) <= 2000));
                        }
                    }
                    $faq = $source['faq'] ?? null;
                    if (is_array($faq) && array_is_list($faq)) {
                        $publicStructured['faq'] = [];
                        foreach (array_slice($faq, 0, 12) as $entry) {
                            if (is_array($entry) && is_string($entry['question'] ?? null)
                                && is_string($entry['answer'] ?? null)
                                && mb_strlen($entry['question']) <= 2000 && mb_strlen($entry['answer']) <= 2000) {
                                $publicStructured['faq'][] = [
                                    'question' => $entry['question'], 'answer' => $entry['answer'],
                                ];
                            }
                        }
                    }
                    $related[$row->service_slug]['landing'] = [
                        'slug' => $snapshot['slug'], 'title' => $snapshot['title'],
                        'content' => $snapshot['content'], 'structured' => $publicStructured,
                        'seo_title' => $snapshot['seo_title'] ?? null,
                        'meta_description' => $snapshot['meta_description'] ?? null,
                    ];
                } elseif (in_array($kind, ['case_study', 'digital_testimonial'], true)
                    && ($snapshot['is_indexable'] ?? false) === true
                    && ($kind !== 'case_study' || ($snapshot['structured_content']['display_enabled'] ?? true) === true)
                    && count($related[$row->service_slug]['related_pages']) < 8) {
                    $related[$row->service_slug]['related_pages'][] = [
                        'slug' => $snapshot['slug'], 'title' => $snapshot['title'], 'purpose' => $kind,
                        'display_order' => (int) ($snapshot['structured_content']['display_order'] ?? 100),
                    ];
                }
            }
            foreach ($related as &$entry) {
                usort($entry['related_pages'], function (array $left, array $right): int {
                    if ($left['purpose'] === $right['purpose']) {
                        return [$left['display_order'] ?? 100, $left['slug']] <=> [$right['display_order'] ?? 100, $right['slug']];
                    }
                    if ($left['purpose'] === 'digital_testimonial') {
                        return 1;
                    }
                    if ($right['purpose'] === 'digital_testimonial') {
                        return -1;
                    }

                    return [$left['display_order'] ?? 100, $left['slug']] <=> [$right['display_order'] ?? 100, $right['slug']];
                });
            }
            unset($entry);
        }
        $items = array_map(fn (array $item) => [...$item, ...($related[$item['slug']] ?? [
            'landing' => null, 'related_pages' => [],
        ])], $items);

        return ['items' => $items];
    }

    public function assertCommerce(): void
    {
        $this->assertScope('commerce');
    }

    public function cartQuote(array $input): array
    {
        $this->assertScope('commerce');
        $data = Validator::make($input, [
            'lines' => 'required|array|min:1|max:50',
            'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity' => 'required|integer|min:1|max:10000',
            'lines.*.variant_key' => 'sometimes|string|max:100',
        ])->validate();
        $ids = collect($data['lines'])->pluck('product_id');
        if ($ids->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Each product may appear once.']);
        }

        return DB::transaction(function () use ($data) {
            $items = [];
            $total = '0.00';
            $outlet = null;
            foreach (collect($data['lines'])->sortBy('product_id') as $line) {
                $row = DB::table('product_listings as l')->join('products as p', 'p.id', '=', 'l.product_id')
                    ->where('p.public_id', $line['product_id'])->where('l.is_online', true)
                    ->where('p.isDeleted', false)->whereNull('p.archived_at')
                    ->select('p.*', 'l.slug as listing_slug')->lockForUpdate()->firstOrFail();
                $product = Product::findOrFail($row->id);
                if ($outlet !== null && $outlet !== $product->outlet_id) {
                    throw ValidationException::withMessages(['lines' => 'A commerce cart must use one outlet.']);
                }
                $outlet = $product->outlet_id;
                $variant = $line['variant_key'] ?? 'standard';
                $available = $this->stock->select($product, (int) $line['quantity'], $variant);
                $unit = SourceRow::money((string) $product->sale_price);
                $lineTotal = bcmul($unit, (string) $line['quantity'], 2);
                $total = bcadd($total, $lineTotal, 2);
                $items[] = ['product_id' => $product->public_id, 'slug' => $row->listing_slug,
                    'name' => $product->name, 'variant_key' => $variant, 'quantity' => (int) $line['quantity'],
                    'unit_price' => $unit, 'line_total' => $lineTotal,
                    'serialized_units_selected' => $product->track_imei ? $available->count() : null];
            }
            $snapshot = ['currency' => 'PKR', 'items' => $items, 'subtotal' => $total,
                'checkout_reprices' => true, 'outlet_id' => (string) $outlet];
            $snapshot['quote_sha256'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $snapshot;
        }, 2);
    }

    public function orders(CustomerAccount $customer, array $input): array
    {
        $this->capabilities->assertHistoricalAllowed('order.status');
        $data = Validator::make($input, ['limit' => 'sometimes|integer|min:1|max:20', 'after' => 'nullable|string|max:120'])->validate();
        $limit = (int) ($data['limit'] ?? 10);
        $after = $this->decodeCursor($data['after'] ?? null);
        $query = DB::table('orders')->where('user_id', $customer->id)
            ->when($after !== null, fn ($q) => $q->where('id', '<', $after))->orderByDesc('id')->limit($limit + 1);
        $rows = $query->get(['id', 'public_id', 'order_number', 'order_type', 'status', 'fulfillment_status',
            'payment_status', 'subtotal', 'total', 'currency', 'created_at', 'updated_at']);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $last = $rows->last();

        return ['items' => $rows->map(fn ($row) => $this->orderSummary($row))->all(),
            'page' => ['limit' => $limit, 'has_more' => $hasMore,
                'next_cursor' => $hasMore && $last ? $this->encodeCursor((int) $last->id) : null]];
    }

    public function order(CustomerAccount $customer, string $publicId): array
    {
        $this->capabilities->assertHistoricalAllowed('order.status');
        $order = DB::table('orders')->where('public_id', $publicId)->where('user_id', $customer->id)->firstOrFail();
        $items = DB::table('order_items')->where('order_id', $order->id)->orderBy('id')
            ->get(['item_type', 'title', 'quantity', 'unit_price', 'line_total', 'external_reference', 'pos_variant_key'])
            ->map(fn ($row) => (array) $row)->all();
        $payments = DB::table('payments')->where('order_id', $order->id)->orderBy('id')
            ->get(['public_id', 'gateway', 'status', 'amount', 'currency', 'initiated_at', 'paid_at'])
            ->map(fn ($row) => (array) $row)->all();

        $terms = DB::table('website_order_payment_terms')->where('order_id', $order->id)
            ->first(['gateway', 'label', 'instructions', 'cod_min_amount', 'cod_max_amount', 'presentation_version']);

        return $this->orderSummary($order) + ['items' => $items, 'payments' => $payments,
            'payment_terms' => $terms ? (array) $terms : null,
            'signed_access_url' => URL::temporarySignedRoute('api.customer.orders.signed', now()->addMinutes(30), ['order' => $order->public_id])];
    }

    public function signedOrder(string $publicId): array
    {
        $this->capabilities->assertHistoricalAllowed('order.status');
        $order = DB::table('orders')->where('public_id', $publicId)->firstOrFail();

        return $this->orderSummary($order);
    }

    public function assertOwnedPayment(CustomerAccount $customer, string $paymentPublicId): void
    {
        abort_unless(DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.public_id', $paymentPublicId)->where('o.user_id', $customer->id)->exists(), 404);
    }

    private function productProjection(object $row): array
    {
        $product = Product::findOrFail($row->product_id);
        $snapshot = $this->stock->snapshot((int) $row->product_id);

        return [
            'id' => $row->product_public_id,
            'listing_id' => $row->listing_public_id,
            'slug' => $row->slug,
            'name' => $row->name,
            'brand' => $product->brandDisplay(),
            'model' => $row->model,
            'category' => ['code' => $row->category, 'label' => $product->categoryDisplay()],
            'subcategory' => $product->subcategoryCode() ? ['code' => $product->subcategoryCode(), 'label' => $product->subcategoryDisplay()] : null,
            'price' => (string) $row->sale_price,
            'currency' => 'PKR',
            'availability' => ['in_stock' => $snapshot['available'] > 0, 'quantity' => $snapshot['available']],
            'warranty_type' => $row->warranty_type,
            'warranty_summary' => $row->warranty_summary ?? null,
            'specs' => ['ram_gb' => $product->ram_gb, 'storage_gb' => $product->storage_gb,
                'pta_statuses' => $snapshot['units']->pluck('pta_status')->filter()->unique()->take(4)->values()->all()],
            'colors' => $snapshot['units']->pluck('color')->filter()->unique()->take(6)->values()->all(),
            'image_url' => $row->image_url,
        ];
    }

    private function publicVariants(Product $product): array
    {
        $snapshot = $this->stock->snapshot($product->id);
        if (! $product->track_imei) {
            return [[
                'key' => 'standard',
                'label' => 'Standard',
                'color' => null,
                'condition' => null,
                'pta_status' => null,
                'carrier_lock_status' => null,
                'mdm_status' => null,
                'availability' => ['in_stock' => $snapshot['available'] > 0, 'quantity' => $snapshot['available']],
            ]];
        }

        return $snapshot['units']
            ->groupBy(fn ($unit) => ProductVariantKey::forUnit($unit))
            ->map(function ($units, string $key) {
                $first = $units->first();

                return [
                    'key' => $key,
                    'label' => collect([$first->color, $first->condition, $first->pta_status, $first->carrier_lock_status, $first->mdm_status])
                        ->filter(fn ($value) => filled($value))->implode(' · '),
                    'color' => $first->color,
                    'condition' => $first->condition,
                    'pta_status' => $first->pta_status,
                    'carrier_lock_status' => $first->carrier_lock_status,
                    'mdm_status' => $first->mdm_status,
                    'availability' => ['in_stock' => $units->isNotEmpty(), 'quantity' => $units->count()],
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function approvedReviews(int $listingId, int $limit): array
    {
        return DB::table('product_reviews')->where('product_listing_id', $listingId)->where('status', 'approved')
            ->orderByDesc('approved_at')->orderByDesc('id')->limit($limit)
            ->get(['rating', 'title', 'body', 'approved_at'])
            ->map(fn ($row) => ['rating' => (int) $row->rating, 'title' => $row->title,
                'body' => $row->body, 'approved_at' => $row->approved_at])->all();
    }

    private function softwarePublic(string $slug): array
    {
        $slug = $this->slug($slug);

        return $this->cache->remember('cms.software', 'api:v1:software:'.$slug,
            fn () => $this->cms->publicSoftware($slug));
    }

    private function orderSummary(object $row): array
    {
        return [
            'id' => $row->public_id,
            'number' => $row->order_number,
            'type' => $row->order_type,
            'status' => $row->status,
            'fulfillment_status' => $row->fulfillment_status,
            'payment_status' => $row->payment_status,
            'subtotal' => (string) $row->subtotal,
            'total' => (string) $row->total,
            'currency' => $row->currency,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    private function assertScope(string $scope): void
    {
        abort_unless($this->capabilities->allowsScope($scope), 404, 'The requested Website capability is not active.');
    }

    private function encodeCatalogueSortCursor(string $sort, int $id): string
    {
        return rtrim(strtr(base64_encode('v2:'.$sort.':'.$id), '+/', '-_'), '=');
    }

    private function decodeCatalogueSortCursor(?string $cursor, string $sort): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $raw = strtr($cursor, '-_', '+/');
        $decoded = base64_decode($raw.str_repeat('=', (4 - strlen($raw) % 4) % 4), true);
        if (! is_string($decoded) || ! preg_match('/\Av2:([a-z_]+):([1-9][0-9]*)\z/', $decoded, $match)
            || $match[1] !== $sort) {
            throw ValidationException::withMessages(['after' => 'Invalid pagination cursor.']);
        }

        return (int) $match[2];
    }

    private function encodeCursor(int $id): string
    {
        return rtrim(strtr(base64_encode('v1:'.$id), '+/', '-_'), '=');
    }

    private function decodeCursor(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $raw = strtr($cursor, '-_', '+/');
        $decoded = base64_decode($raw.str_repeat('=', (4 - strlen($raw) % 4) % 4), true);
        if (! is_string($decoded) || ! preg_match('/\Av1:([1-9][0-9]*)\z/', $decoded, $match)) {
            throw ValidationException::withMessages(['after' => 'Invalid pagination cursor.']);
        }

        return (int) $match[1];
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        abort_unless((bool) preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $value) && strlen($value) <= 160, 404);

        return $value;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
