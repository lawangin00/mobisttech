<?php

namespace App\Http\Controllers;

use App\Commerce\WebsitePaymentAdministration;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class WebsitePaymentAdministrationController extends Controller
{
    public function page(WebsitePaymentAdministration $payments)
    {
        $actor = Auth::guard('admin')->user() ?? abort(401);
        $channels = $payments->overview($actor);

        return Inertia::render('website-payment-settings', [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
            'channels' => $channels,
        ])->toResponse(request())->header('Cache-Control', 'private, no-store');
    }

    public function channels(WebsitePaymentAdministration $payments)
    {
        $actor = Auth::guard('admin')->user() ?? abort(401);

        return response()->json(['data' => $payments->overview($actor)])
            ->header('Cache-Control', 'private, no-store');
    }
}
