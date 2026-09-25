<?php

namespace App\Http\Controllers;

use App\Api\ApiResponse;
use App\Api\WebsiteApi;
use App\Commerce\OrderTransactions;
use App\Digital\DigitalServiceLeads;
use App\Engagement\CustomerEngagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class WebsiteApiController extends Controller
{
    public function __construct(private ApiResponse $responses) {}

    public function profile(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->profile(), 'website-profile.v1', 60);
    }

    public function catalogue(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->catalogue($request->query()), 'catalogue-page.v1', 30);
    }

    public function product(Request $request, WebsiteApi $api, string $slug)
    {
        return $this->responses->public($request, $api->product($slug), 'catalogue-product.v1', 30);
    }

    public function categories(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->categories(), 'catalogue-categories.v1', 60);
    }

    public function contentIndex(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->contentIndex(), 'content-index.v1', 60);
    }

    public function page(Request $request, WebsiteApi $api, string $slug)
    {
        return $this->responses->public($request, $api->page($slug), 'published-page.v1', 60);
    }

    public function brandMedia(WebsiteApi $api, string $role, int $media)
    {
        // The current published role is the ONLY public authority. Draft/historical/foreign assets 404.
        $selected = $api->contentIndex()['branding'][$role] ?? null;
        abort_unless($media > 0 && $selected === $media, 404);
        $asset = DB::table('site_media_assets')->where('id', $media)->where('status', 'active')->first();
        abort_unless($asset && $asset->disk === 'local'
            && in_array($asset->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true)
            && $asset->byte_size > 0 && $asset->byte_size <= 10 * 1024 * 1024
            && preg_match('/\Acms\/[0-9a-f-]+\.(?:png|jpe?g|webp)\z/i', $asset->path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->path), 404);

        return response($disk->get($asset->path), 200, [
            'Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function pageMedia(WebsiteApi $api, string $slug, int $media)
    {
        // Public access requires the exact published, mode-allowed page's image reference.
        $page = $api->page($slug);
        $allowed = [(int) ($page['snapshot']['social_image_media_id'] ?? 0)];
        if (($page['snapshot']['content_purpose'] ?? null) === 'case_study') {
            foreach ($page['snapshot']['structured_content']['screenshot_media_ids'] ?? [] as $id) {
                if (is_int($id)) {
                    $allowed[] = $id;
                }
            }
        }
        abort_unless($media > 0 && in_array($media, $allowed, true), 404);
        $asset = DB::table('site_media_assets')->where('id', $media)->where('status', 'active')->first();
        abort_unless($asset && $asset->disk === 'local' && in_array($asset->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true)
            && preg_match('/\Acms\/[0-9a-f-]+\.(?:png|jpe?g|webp)\z/i', $asset->path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->path), 404);

        return response($disk->get($asset->path), 200, [
            'Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function policies(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, ['items' => $api->policies()], 'published-policies.v1', 300);
    }

    public function softwareRedirect(Request $request)
    {
        $path = $request->query('path');
        abort_unless(is_string($path) && strlen($path) <= 500
            && preg_match('~\A/software/[a-z0-9-]+(?:/(?:privacy|terms|faq|releases(?:/[a-z0-9.\-]+)?))?\z~', $path), 404);
        $redirect = DB::table('cms_route_redirects as r')
            ->join('software_products as p', 'p.id', '=', 'r.software_product_id')
            ->where('r.from_path', $path)->where('p.lifecycle_state', 'published')
            ->first(['r.to_path', 'p.slug']);
        abort_unless($redirect && ($redirect->to_path === '/software/'.$redirect->slug
            || str_starts_with($redirect->to_path, '/software/'.$redirect->slug.'/')), 404);

        return response()->json(['data' => ['to_path' => $redirect->to_path]])
            ->header('Cache-Control', 'no-store');
    }

    public function software(Request $request, WebsiteApi $api, string $slug)
    {
        return $this->responses->public($request, $api->software($slug), 'software-overview.v1', 60);
    }

    public function softwareMedia(WebsiteApi $api, string $slug, int $media)
    {
        // Published snapshot, not drafts or a publicly enumerable media library, authorizes access.
        $overview = $api->software($slug)['overview'];
        $ids = array_filter([
            $overview['logo_media_id'] ?? null, $overview['icon_media_id'] ?? null,
            $overview['hero_media_id'] ?? null, $overview['seo']['social_image_media_id'] ?? null,
            ...($overview['screenshot_media_ids'] ?? []),
        ]);
        abort_unless(in_array($media, array_map('intval', $ids), true), 404);
        $asset = DB::table('site_media_assets')->where('id', $media)->where('status', 'active')->first();
        abort_unless($asset && $asset->disk === 'local' && in_array($asset->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true)
            && preg_match('/\Acms\/[0-9a-f-]+\.(?:png|jpe?g|webp)\z/i', $asset->path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->path), 404);

        return response($disk->get($asset->path), 200, [
            'Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function softwareSection(Request $request, WebsiteApi $api, string $slug, string $section)
    {
        return $this->responses->public($request, $api->softwareSection($slug, $section), 'software-'.$section.'.v1', 60);
    }

    public function consultation(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->consultation(), 'consultation-availability.v1', 60);
    }

    public function services(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->services(), 'digital-services.v1', 30);
    }

    public function guestWishlist(Request $request, CustomerEngagement $engagement)
    {
        $token = $request->validate(['guest_token' => ['required', 'string', 'min:32', 'max:128']])['guest_token'];

        return $this->responses->private(['items' => $engagement->wishlist(null, $token)], 'guest-wishlist.v1');
    }

    public function saveGuestWishlist(Request $request, CustomerEngagement $engagement, string $product)
    {
        $token = $request->validate(['guest_token' => ['required', 'string', 'min:32', 'max:128']])['guest_token'];

        return $this->responses->private($engagement->saveForLater(null, $token, $product), 'guest-wishlist-item.v1', 201);
    }

    public function removeGuestWishlist(Request $request, CustomerEngagement $engagement, string $product)
    {
        $token = $request->validate(['guest_token' => ['required', 'string', 'min:32', 'max:128']])['guest_token'];

        return $this->responses->private($engagement->removeSaved(null, $token, $product), 'guest-wishlist-item.v1');
    }

    public function enquiry(Request $request, DigitalServiceLeads $leads)
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '', 422, 'Idempotency-Key is required.');
        $fingerprint = hash('sha256', ($request->ip() ?? 'unknown').'|'.substr((string) $request->userAgent(), 0, 300));
        $result = $leads->submit($key, $fingerprint, $request->all(), []);

        return $this->responses->private($result, 'digital-enquiry.v1', 201);
    }

    public function paymentCallback(Request $request, OrderTransactions $orders, string $gateway)
    {
        abort_unless(in_array($gateway, ['jazzcash', 'easypaisa', 'card'], true), 404);
        $result = $orders->callback($gateway, $request->all());

        return $this->responses->private($result, 'payment-callback.v1');
    }
}
