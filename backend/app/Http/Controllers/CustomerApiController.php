<?php

namespace App\Http\Controllers;

use App\Api\ApiResponse;
use App\Api\WebsiteApi;
use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProviders;
use App\Commerce\ProductReviews;
use App\Digital\ClientProjectServices;
use App\Engagement\CustomerEngagement;
use App\Identity\CustomerIdentity;
use App\Models\CustomerAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CustomerApiController extends Controller
{
    public function __construct(private ApiResponse $responses) {}

    public function cartQuote(Request $request, WebsiteApi $api)
    {
        return $this->responses->private($api->cartQuote($request->all()), 'cart-quote.v1');
    }

    public function checkoutChannels(WebsiteApi $api, PaymentProviders $providers)
    {
        $api->assertCommerce();

        return $this->responses->private(['items' => $providers->checkoutChannels()], 'checkout-channels.v1');
    }

    public function checkout(Request $request, WebsiteApi $api, OrderTransactions $orders)
    {
        $customer = $this->customer();
        $api->cartQuote(['lines' => $request->input('lines', [])]);
        $result = $orders->checkout($this->scope($customer), $customer, $this->idempotency($request), $request->all());

        return $this->responses->private($result, 'order-create.v1', 201);
    }

    public function orders(Request $request, WebsiteApi $api)
    {
        return $this->responses->private($api->orders($this->customer(), $request->query()), 'customer-orders.v1');
    }

    public function order(WebsiteApi $api, string $order)
    {
        return $this->responses->private($api->order($this->customer(), $order), 'customer-order.v1');
    }

    public function signedOrder(WebsiteApi $api, string $order)
    {
        return $this->responses->private($api->signedOrder($order), 'customer-order-signed.v1');
    }

    public function cancel(Request $request, OrderTransactions $orders, string $order)
    {
        $customer = $this->customer();
        $result = $orders->cancel($this->scope($customer), $customer, $order, $this->idempotency($request));

        return $this->responses->private($result, 'order-cancel.v1');
    }

    public function retryPayment(Request $request, WebsiteApi $api, OrderTransactions $orders, string $order)
    {
        $api->assertCommerce();
        $customer = $this->customer();
        $gateway = (string) $request->input('gateway');
        abort_unless(in_array($gateway, ['jazzcash', 'easypaisa', 'card'], true), 422);
        $result = $orders->retry($this->scope($customer), $customer, $order, $this->idempotency($request), $gateway);

        return $this->responses->private($result, 'payment-retry.v1', 201);
    }

    public function initiatePayment(WebsiteApi $api, OrderTransactions $orders, string $payment)
    {
        $customer = $this->customer();
        $api->assertOwnedPayment($customer, $payment);

        return $this->responses->private($orders->initiate($payment), 'payment-initiation.v1');
    }

    public function milestone(Request $request, OrderTransactions $orders)
    {
        $result = $orders->milestone($this->customer(), $this->idempotency($request), $request->all());

        return $this->responses->private($result, 'milestone-payment.v1', 201);
    }

    public function projects(ClientProjectServices $projects)
    {
        return $this->responses->private(['items' => $projects->customerProjects($this->customer())], 'client-projects.v1');
    }

    public function projectPaymentChannels(PaymentProviders $providers)
    {
        $items = array_values(array_filter(
            $providers->checkoutChannels(),
            fn (array $channel) => $channel['code'] !== 'cod',
        ));

        return $this->responses->private(['items' => $items], 'project-payment-channels.v1');
    }

    public function project(ClientProjectServices $projects, string $project)
    {
        return $this->responses->private($projects->portal($this->customer(), $project), 'client-project.v1');
    }

    public function projectReference(Request $request, ClientProjectServices $projects, string $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base64' => ['required', 'string', 'max:15728640'],
        ]);
        $contents = base64_decode($data['base64'], true);
        abort_if($contents === false, 422, 'Private reference payload is not valid base64.');

        return $this->responses->private(
            $projects->uploadReference($this->customer(), $project, ['name' => $data['name'], 'contents' => $contents]),
            'client-project-file.v1',
            201,
        );
    }

    public function projectFile(ClientProjectServices $projects, string $project, string $file)
    {
        $payload = $projects->download($this->customer(), $project, $file);
        $contents = $payload['contents'];
        unset($payload['contents']);

        return response($contents)->header('Content-Type', $payload['mime_type'])
            ->header('Content-Disposition', 'attachment; filename="'.addslashes($payload['name']).'"')
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function wishlist(CustomerEngagement $engagement)
    {
        return $this->responses->private(['items' => $engagement->wishlist($this->customer(), null)], 'wishlist.v1');
    }

    public function saveWishlist(CustomerEngagement $engagement, string $product)
    {
        return $this->responses->private($engagement->saveForLater($this->customer(), null, $product), 'wishlist-item.v1', 201);
    }

    public function removeWishlist(CustomerEngagement $engagement, string $product)
    {
        return $this->responses->private($engagement->removeSaved($this->customer(), null, $product), 'wishlist-item.v1');
    }

    public function claimWishlist(Request $request, CustomerEngagement $engagement)
    {
        $token = $request->validate(['guest_token' => ['required', 'string', 'min:32', 'max:128']])['guest_token'];

        return $this->responses->private(['items' => $engagement->claimGuestWishlist($this->customer(), $token)], 'wishlist.v1');
    }

    public function notificationPreferences(CustomerEngagement $engagement)
    {
        return $this->responses->private($engagement->preferences($this->customer()), 'notification-preferences.v1');
    }

    public function updateNotificationPreferences(Request $request, CustomerEngagement $engagement)
    {
        return $this->responses->private($engagement->updatePreferences($this->customer(), $request->all()), 'notification-preferences.v1');
    }

    public function subscriptions(CustomerEngagement $engagement)
    {
        return $this->responses->private(['items' => $engagement->subscriptions($this->customer())], 'product-subscriptions.v1');
    }

    public function subscribe(Request $request, CustomerEngagement $engagement, string $product)
    {
        return $this->responses->private($engagement->subscribe($this->customer(), $product, $request->all()), 'product-subscription.v1', 201);
    }

    public function unsubscribe(Request $request, CustomerEngagement $engagement, string $product)
    {
        $events = $request->input('events');

        return $this->responses->private($engagement->unsubscribe($this->customer(), $product, is_array($events) ? $events : null), 'product-subscription.v1');
    }

    public function reviews(ProductReviews $reviews)
    {
        return $this->responses->private(['items' => $reviews->mine($this->customer())], 'customer-reviews.v1');
    }

    public function reviewEligibility(ProductReviews $reviews)
    {
        return $this->responses->private(['items' => $reviews->eligible($this->customer())], 'customer-review-eligibility.v1');
    }

    public function loyalty(CustomerIdentity $identity)
    {
        $customer = $this->customer();
        $owned = $identity->forAccount($customer);
        $config = DB::table('loyalty_configurations')->orderByDesc('version')->first();
        $account = DB::table('loyalty_accounts')->where('customer_id', $owned->id)->first();

        return $this->responses->private([
            'enabled' => (bool) ($config?->enabled ?? false),
            'balance_points' => (int) ($account?->balance_points ?? 0),
            'min_redeem_points' => $config ? (int) $config->min_redeem_points : null,
            'max_redeem_points' => $config ? (int) $config->max_redeem_points : null,
            'redemption_value' => $config ? (string) $config->redemption_value : null,
            'expiry_days' => $config ? (int) $config->expiry_days : null,
        ], 'customer-loyalty.v1');
    }

    public function submitReview(Request $request, ProductReviews $reviews)
    {
        return $this->responses->private($reviews->submit($this->customer(), $request->all()), 'customer-review.v1', 201);
    }

    private function customer(): CustomerAccount
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof CustomerAccount && $customer->usable(), 401);

        return $customer;
    }

    private function idempotency(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '' || strlen($key) > 120, 422, 'A valid Idempotency-Key header is required.');

        return $key;
    }

    private function scope(CustomerAccount $customer): string
    {
        return $customer::class.':'.$customer->id;
    }
}
