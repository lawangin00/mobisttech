<?php

namespace App\Catalog;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProductWebsitePublication
{
    public function save(IdentityAccount $actor, Outlet $outlet, string $publicId, array $input): array
    {
        $data = Validator::make($input, [
            'slug' => ['required', 'string', 'min:3', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::notIn(['mobiles', 'tablets', 'accessories'])],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'is_online' => ['required', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ])->validate();
        $data['description'] = trim($data['description']);
        abort_if(strlen($data['description']) < 10, 422, 'A public description is required.');

        return DB::transaction(function () use ($actor, $outlet, $publicId, $data) {
            $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOutlet->status == false && $lockedOutlet->archived_at === null, 403);
            abort_unless(app(Access::class)->allows($actor, 'shop.inventory', $lockedOutlet)
                && app(Access::class)->allows($actor, 'website.content.manage'), 403);
            $product = Product::where('public_id', $publicId)->where('outlet_id', $lockedOutlet->id)
                ->where('isDeleted', false)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $listing = DB::table('product_listings')->where('product_id', $product->id)->lockForUpdate()->first();
            abort_if($listing && $listing->external_source !== 'pos', 409, 'An imported listing cannot be overwritten.');
            abort_if((int) ($listing->version ?? 0) !== (int) $data['expected_version'], 409, 'Website listing changed; reload.');
            $duplicate = DB::table('product_listings')->where('slug', $data['slug'])
                ->when($listing, fn ($query) => $query->where('id', '<>', $listing->id))->exists();
            abort_if($duplicate, 422, 'Website slug is already in use.');
            $version = ($listing ? (int) $listing->version : 0) + 1;
            $public = $listing?->public_id ?? (string) Str::uuid();
            $values = ['slug' => $data['slug'], 'name' => $product->name,
                'brand' => $product->brand, 'model' => $product->model,
                'category' => $product->category, 'description' => $data['description'],
                'warranty_summary' => $product->warranty_type, 'is_online' => $data['is_online'],
                'version' => $version, 'updated_at' => now()];
            if ($listing) {
                DB::table('product_listings')->where('id', $listing->id)->update($values);
            } else {
                DB::table('product_listings')->insert($values + [
                    'product_id' => $product->id, 'public_id' => $public,
                    'external_source' => 'pos', 'external_id' => $product->public_id, 'created_at' => now(),
                ]);
            }
            CatalogChanged::record('product_listing', $public, $version);

            return ['id' => $public, 'slug' => $data['slug'], 'description' => $data['description'],
                'is_online' => (bool) $data['is_online'], 'version' => $version];
        }, 2);
    }
}
