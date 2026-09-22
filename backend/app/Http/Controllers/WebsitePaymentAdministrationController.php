<?php

namespace App\Http\Controllers;

use App\Commerce\WebsitePaymentAdministration;
use Illuminate\Http\Request;
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
            'settings' => $payments->settings($actor),
        ])->toResponse(request())->header('Cache-Control', 'private, no-store');
    }

    public function channels(WebsitePaymentAdministration $payments)
    {
        $actor = Auth::guard('admin')->user() ?? abort(401);

        return response()->json(['data' => $payments->overview($actor)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function draft(Request $request, WebsitePaymentAdministration $payments)
    {
        $actor = Auth::guard('admin')->user() ?? abort(401);
        $saved = $payments->saveDraft($actor, $request->all());
        if ($request->expectsJson()) {
            return response()->json(['data' => $saved], 201)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->back(303);
    }

    public function publish(Request $request, WebsitePaymentAdministration $payments, int $revision)
    {
        $actor = Auth::guard('admin')->user() ?? abort(401);
        $published = $payments->publish($actor, $revision);
        if ($request->expectsJson()) {
            return response()->json(['data' => $published])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->back(303);
    }
}
