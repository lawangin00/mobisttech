<?php

namespace App\Http\Controllers;

use App\Commerce\WebsiteCommerceAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class WebsiteCommerceAdministrationController extends Controller
{
    public function orders(Request $request, WebsiteCommerceAdministration $service)
    {
        return response()->json(['data' => $service->orders($this->actor(), $request->query())]);
    }

    public function updateOrder(Request $request, string $order, WebsiteCommerceAdministration $service)
    {
        return response()->json(['data' => $service->updateOrder($this->actor(), $order, $request->all())]);
    }

    public function ordersCsv(Request $request, WebsiteCommerceAdministration $service)
    {
        return response()->json(['data' => $service->ordersCsv($this->actor(), $request->query())]);
    }

    public function reviews(Request $request, WebsiteCommerceAdministration $service)
    {
        return response()->json(['data' => $service->reviews($this->actor(), (string) $request->query('status', 'pending'))]);
    }

    public function moderateReview(Request $request, int $review, WebsiteCommerceAdministration $service)
    {
        return response()->json(['data' => $service->moderateReview($this->actor(), $review, $request->all())]);
    }

    private function actor()
    {
        return Auth::guard('admin')->user() ?? abort(401);
    }
}
