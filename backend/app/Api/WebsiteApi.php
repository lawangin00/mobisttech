<?php

namespace App\Api;

use App\Addendum\WebsiteCapabilities;
use App\Business\BusinessProfile;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'limit' => 'sometimes|integer|min:1|max:24',
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
                $columns = ['l.id as listing_id', 'l.public_id as listing_public_id', 'l.slug', 'l.image_url',
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
                $payload['variants'] = $this->publicVariants(Product::findOrFail($row->product_id));

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
        $pages = DB::table('site_managed_pages')
            ->where('publish_state', 'published')->whereNotNull('current_revision_id')->where('is_indexable', true)
            ->orderBy('title')->get(['slug', 'title', 'content_purpose', 'capability_scope', 'show_in_navigation'])
            ->filter(fn ($row) => $this->capabilities->allowsScope((string) $row->capability_scope))
            ->map(fn ($row) => [
                'slug' => $row->slug, 'title' => $row->title, 'purpose' => $row->content_purpose,
                'scope' => $row->capability_scope, 'show_in_navigation' => (bool) $row->show_in_navigation,
            ])->values()->all();

        $software = DB::table('software_products')->where('lifecycle_state', 'published')->whereNotNull('current_revision_id')
            ->orderBy('name')->get(['slug', 'name'])->map(fn ($row) => [
                'slug' => $row->slug, 'name' => $row->name, 'routes' => $this->cms->publicSoftware($row->slug)['routes'],
            ])->all();

        $navigation = DB::table('site_navigation_items')->where('is_visible', true)->where('is_enabled', true)
            ->orderBy('sort_order')->orderBy('id')->get(['key', 'label', 'destination_type', 'destination_key',
                'destination_payload', 'target_behavior', 'capability_scope'])
            ->filter(fn ($row) => $this->capabilities->allowsScope((string) $row->capability_scope))
            ->map(fn ($row) => [
                'key' => $row->key, 'label' => $row->label, 'destination_type' => $row->destination_type,
                'destination_key' => $row->destination_key,
                'destination_payload' => $row->destination_payload ? json_decode($row->destination_payload, true, flags: JSON_THROW_ON_ERROR) : null,
                'target_behavior' => $row->target_behavior, 'scope' => $row->capability_scope,
            ])->values()->all();

        return [
            'pages' => $pages,
            'policies' => array_map(fn ($policy) => array_intersect_key($policy, array_flip([
                'type', 'slug', 'title', 'footer_destination', 'effective_date', 'version',
            ])), $this->cms->publicPolicies()),
            'software' => $software,
            'navigation' => $navigation,
        ];
    }

    public function page(string $slug): array
    {
        $payload = $this->cache->remember('cms.pages', 'api:v1:page:'.$slug, fn () => $this->cms->publicPage($slug));
        $scope = (string) ($payload['snapshot']['capability_scope'] ?? 'common');
        abort_unless($this->capabilities->allowsScope($scope), 404);

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

        return $this->orderSummary($order) + ['items' => $items, 'payments' => $payments];
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
