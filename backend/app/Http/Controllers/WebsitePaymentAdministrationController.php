<?php

namespace App\Http\Controllers;

use App\Commerce\WebsitePaymentAdministration;
use Illuminate\Support\Facades\Auth;

final class WebsitePaymentAdministrationController extends Controller
{
    public function channels(WebsitePaymentAdministration $payments)
    {
        $actor = Auth::guard('admin')->user() ?? abort(401);

        return response()->json(['data' => $payments->overview($actor)])
            ->header('Cache-Control', 'private, no-store');
    }
}
