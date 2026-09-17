<?php

namespace App\Http\Controllers;

use App\Api\ApiResponse;
use App\Api\WebsiteApi;
use App\Commerce\OrderTransactions;
use App\Digital\DigitalServiceLeads;
use Illuminate\Http\Request;

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

    public function page(Request $request, WebsiteApi $api, string $slug)
    {
        return $this->responses->public($request, $api->page($slug), 'published-page.v1', 60);
    }

    public function policies(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, ['items' => $api->policies()], 'published-policies.v1', 300);
    }

    public function software(Request $request, WebsiteApi $api, string $slug)
    {
        return $this->responses->public($request, $api->software($slug), 'software-overview.v1', 60);
    }

    public function softwareSection(Request $request, WebsiteApi $api, string $slug, string $section)
    {
        return $this->responses->public($request, $api->softwareSection($slug, $section), 'software-'.$section.'.v1', 60);
    }

    public function services(Request $request, WebsiteApi $api)
    {
        return $this->responses->public($request, $api->services(), 'digital-services.v1', 30);
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
