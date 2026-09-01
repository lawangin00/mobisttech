<?php

namespace App\Http\Controllers;

use App\Business\BusinessProfile;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class BusinessProfileController extends Controller
{
    public function show(BusinessProfile $profile)
    {
        return response()->json(['data' => $profile->current()])->header('Cache-Control', 'public, max-age=300');
    }

    public function update(Request $request, BusinessProfile $profile)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return response()->json(['data' => $profile->update($actor, $request->all())]);
    }
}
