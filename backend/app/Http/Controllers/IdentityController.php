<?php

namespace App\Http\Controllers;

use App\Identity\Access;
use App\Identity\CustomerIdentity;
use App\Identity\IdentityAudit;
use App\Identity\OfflineOwnerRecovery;
use App\Identity\PosSessions;
use App\Identity\RealmSessionPolicy;
use App\Integrations\GmailRecovery;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityController extends Controller
{
    public function csrf(Request $request)
    {
        return response()->json(['data' => ['csrf_token' => $request->session()->token()]]);
    }

    public function register(Request $request)
    {
        $this->only($request, ['name', 'email', 'mobile', 'password', 'password_confirmation']);
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'mobile' => ['required', 'regex:/^03\d{9}$/', 'unique:users,mobile'], 'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed']]);
        $user = DB::transaction(function () use ($data) {
            $user = new CustomerAccount;
            $user->forceFill(['name' => trim($data['name']), 'email' => $data['email'], 'mobile' => $data['mobile'],
                'password' => Hash::make($data['password']), 'is_admin' => false, 'admin_role' => null])->save();
            app(CustomerIdentity::class)->forAccount($user);
            IdentityAudit::record('customer', $user->id, 'registered');

            return $user;
        });
        Auth::guard('customer')->login($user);
        $request->session()->regenerate();
        $request->session()->put('identity_version', $user->auth_version ?? 1);
        app(RealmSessionPolicy::class)->login($request, 'customer');

        return $this->account($request)->setStatusCode(201);
    }

    public function login(Request $request)
    {
        $this->only($request, ['email', 'password', 'remember']);
        $credentials = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:128'], 'remember' => ['sometimes', 'boolean']]);
        $realm = $request->attributes->get('identity_realm');
        $guard = Auth::guard($realm);
        $policy = app(RealmSessionPolicy::class);
        $policy->configureGuard($guard, $realm);
        if ($realm === 'admin' && $request->boolean('remember')) {
            throw ValidationException::withMessages(['remember' => 'Remember Me is not available for Team Members.']);
        }
        $previousId = $guard->id();
        $previousSession = $request->session()->getId();
        $remember = $realm === 'customer' && $request->boolean('remember');
        if (! $guard->attempt(['email' => strtolower(trim($credentials['email'])), 'password' => $credentials['password']], $remember)) {
            IdentityAudit::record($realm, null, 'login_failed');
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $user = $guard->user();
        if ($previousId && $realm === 'admin') {
            DB::table('account_sessions')->where('guard', $realm)->where('account_id', $previousId)
                ->where('session_id', $previousSession)->update(['revoked_at' => now()]);
        }
        $request->session()->regenerate();
        $request->session()->forget(['active_outlet_id', 'operator_admin_id']);
        if ($realm === 'admin') {
            $error = app(PosSessions::class)->register($request, $realm, $user->id);
            if ($error) {
                $guard->logout();
                $request->session()->invalidate();
                abort(403, $error);
            }
        }
        if ($realm === 'customer') {
            app(CustomerIdentity::class)->forAccount($user);
        }
        if ($realm === 'admin' && $user->hasPermission('shops.enter')) {
            $outlets = $user->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')->get();
            if ($outlets->isEmpty()) {
                app(PosSessions::class)->release($request, $realm, $user->id);
                $guard->logout();
                $request->session()->invalidate();
                abort(403, 'No open outlet is assigned.');
            }
            if ($outlets->count() === 1) {
                $request->session()->put(['active_outlet_id' => $outlets->first()->id, 'operator_admin_id' => $user->id]);
            }
        }
        $request->session()->put('identity_version', $user->auth_version);
        $policy->login($request, $realm);
        IdentityAudit::record($realm, $user->id, 'login_succeeded');

        return $this->account($request);
    }

    public function logout(Request $request)
    {
        $realm = $request->attributes->get('identity_realm');
        $user = Auth::guard($realm)->user();
        if ($realm === 'admin') {
            app(PosSessions::class)->release($request, $realm, $user->id);
        }
        IdentityAudit::record($realm, $user->id, 'logout');
        Auth::guard($realm)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['message' => 'Signed out.']]);
    }

    public function account(Request $request)
    {
        if ($request->isMethod('GET')) {
            $this->only($request, []);
        }
        $user = Auth::guard($request->attributes->get('identity_realm'))->user();

        $realm = $request->attributes->get('identity_realm');
        $policy = app(RealmSessionPolicy::class);
        $data = ['id' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile,
            'session_policy' => $policy->publicContract($realm),
            'session_state' => $policy->publicState($request, $realm, $user)];
        if ($user instanceof Admin) {
            $data += ['job_title' => $user->job_title, 'roles' => $user->roleNames(), 'permissions' => $user->effectivePermissions()];
        }

        return response()->json(['data' => $data]);
    }

    public function activity(Request $request)
    {
        $this->only($request, []);
        $realm = $request->attributes->get('identity_realm');
        $policy = app(RealmSessionPolicy::class);
        $user = Auth::guard($realm)->user();
        $policy->recordHumanActivity($request, $realm, $user->id);

        return response()->json(['data' => [
            'session_policy' => $policy->publicContract($realm),
            'session_state' => $policy->publicState($request, $realm, $user),
        ]]);
    }

    public function confirmPassword(Request $request)
    {
        $this->only($request, ['password']);
        $data = $request->validate(['password' => ['required', 'string', 'max:128']]);
        $realm = $request->attributes->get('identity_realm');
        $user = Auth::guard($realm)->user();
        abort_unless(Hash::check($data['password'], $user->password), 422, 'Password is incorrect.');
        app(RealmSessionPolicy::class)->confirmRecentAuthentication($request);
        if ($realm === 'admin') { $request->session()->put('identity_explicit_password_confirmed_at', now()->timestamp); }
        IdentityAudit::record($realm, $user->id, 'recent_authentication_confirmed');

        return response()->json(['data' => ['confirmed' => true, 'valid_for_minutes' => config('identity.sessions.'.$realm.'.recent_auth_minutes')]]);
    }

    public function outlets(Request $request)
    {
        $user = Auth::guard('admin')->user();
        abort_unless($user instanceof Admin && $user->hasPermission('shops.enter'), 403);

        return response()->json(['data' => $user->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')
            ->get(['outlets.public_id', 'outlets.name'])->map(fn ($outlet) => ['id' => $outlet->public_id, 'name' => $outlet->name])]);
    }

    public function selectOutlet(Request $request)
    {
        $this->only($request, ['outlet_id']);
        $data = $request->validate(['outlet_id' => ['required', 'uuid']]);
        $outlet = Outlet::where('public_id', $data['outlet_id'])->first();
        $user = Auth::guard('admin')->user();
        abort_unless($outlet && app(Access::class)->allows($user, 'shops.enter', $outlet), 404);
        $request->session()->put(['active_outlet_id' => $outlet->id, 'operator_admin_id' => $user->id]);
        abort_if(app(PosSessions::class)->register($request, 'admin', $user->id), 403);

        return response()->json(['data' => ['outlet_id' => $outlet->public_id]]);
    }

    /** Disabled and unbound by default; explicit bound owner must confirm a fresh password. */
    public function rotateOfflineOwnerCodes(Request $request, OfflineOwnerRecovery $recovery)
    {
        $this->only($request, ['current_password']);
        $data = $request->validate(['current_password' => ['required', 'string', 'max:128']]);
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $response = response()->json(['data' => $recovery->rotate($actor, $data['current_password'])]);
        return $response->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function redeemOfflineOwnerCode(Request $request, OfflineOwnerRecovery $recovery)
    {
        $this->only($request, ['email', 'code', 'password', 'password_confirmation']);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed']]);
        $recovery->redeem($data['email'], $data['code'], $data['password']);
        return response()->json(['data' => ['message' => 'Password reset. Sign in again.']])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function forgot(Request $request)
    {
        $this->only($request, ['email']);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        abort_unless(config('identity.recovery_delivery_enabled') && DB::table('integration_connections')->where('provider', 'gmail')->where('status', 'connected')->exists(), 503, 'Account recovery delivery is not configured.');
        $realm = $request->attributes->get('identity_realm');
        Password::broker($realm)->sendResetLink(['email' => strtolower(trim($data['email']))], function ($user, $token) use ($realm) {
            if ($user->usable()) {
                app(GmailRecovery::class)->send($user, $realm, $token);
            }
        });

        return response()->json(['data' => ['message' => 'If an eligible account matches, a recovery link has been sent.']]);
    }

    public function reset(Request $request)
    {
        $this->only($request, ['email', 'token', 'password', 'password_confirmation']);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed']]);
        $data['email'] = strtolower(trim($data['email']));
        $realm = $request->attributes->get('identity_realm');
        $status = DB::transaction(function () use ($realm, $data) {
            $model = config('auth.providers.'.config('auth.guards.'.$realm.'.provider').'.model');
            $account = $model::where('email', $data['email'])->lockForUpdate()->first();
            if (! $account || ! $account->usable()) {
                return Password::INVALID_TOKEN;
            }

            return Password::broker($realm)->reset($data, function ($user, $password) use ($realm) {
                $this->replacePassword($realm, $user, $password);
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'This recovery link is invalid or expired.']);
        }

        return response()->json(['data' => ['message' => 'Password reset. Sign in again.']]);
    }

    public function changePassword(Request $request)
    {
        $this->only($request, ['current_password', 'password', 'password_confirmation']);
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed']]);
        $realm = $request->attributes->get('identity_realm');
        $user = Auth::guard($realm)->user();
        DB::transaction(function () use ($user, $data, $realm) {
            $locked = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(password_verify($data['current_password'], $locked->password), 422, 'Current password is incorrect.');
            $this->replacePassword($realm, $locked, $data['password']);
        });
        Auth::guard($realm)->logout();
        $request->session()->invalidate();

        return response()->json(['data' => ['message' => 'Password updated. Sign in again.']]);
    }

    private function replacePassword(string $realm, $user, string $password): void
    {
        $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60), 'auth_version' => $user->auth_version + 1])->save();
        DB::table('account_sessions')->where('guard', $realm)->where('account_id', $user->id)->update(['revoked_at' => now()]);
        if ($realm === 'admin') {
            // A normal password change/email reset revokes stale offline owner codes as well.
            DB::table('owner_offline_recovery_codes')->where('admin_id', $user->id)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }
        IdentityAudit::record($realm, $user->id, 'password_replaced');
        event(new PasswordReset($user));
    }

    private function only(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), [...$allowed, '_token']);
        if ($unexpected) {
            throw ValidationException::withMessages(['request' => 'Unexpected fields are not accepted.']);
        }
    }
}
