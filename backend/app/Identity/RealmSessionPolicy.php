<?php

namespace App\Identity;

use App\Models\Admin;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RealmSessionPolicy
{
    public function configureRequest(string $realm): void
    {
        $policy = $this->for($realm);
        config(['session.lifetime' => $policy['inactivity_minutes'], 'session.expire_on_close' => true]);
    }

    public function configureGuard(StatefulGuard $guard, string $realm): void
    {
        if (method_exists($guard, 'setRememberDuration')) {
            $guard->setRememberDuration($realm === 'customer' ? $this->for('customer')['remember_minutes'] : 0);
        }
    }

    public function login(Request $request, string $realm): void
    {
        $now = now()->timestamp;
        $request->session()->put(['identity_last_human_activity_at' => $now, 'identity_recent_auth_at' => $now]);
    }

    public function assertActive(Request $request, string $realm, object $user): ?string
    {
        $last = (int) $request->session()->get('identity_last_human_activity_at', 0);
        if ($realm === 'admin' && $user instanceof Admin) {
            $databaseLast = DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $user->id)
                ->where('session_id', $request->session()->getId())->whereNull('revoked_at')->value('last_human_activity');
            $last = $databaseLast ? max($last, strtotime((string) $databaseLast)) : $last;
        }
        if ($last <= 0 || now()->timestamp - $last >= $this->for($realm)['inactivity_minutes'] * 60) {
            return 'Session expired due to inactivity.';
        }

        return null;
    }

    public function recordHumanActivity(Request $request, string $realm, ?int $accountId = null): void
    {
        if (! $this->isHumanActivity($request)) {
            return;
        }
        $request->session()->put('identity_last_human_activity_at', now()->timestamp);
        if ($realm === 'admin' && $accountId) {
            DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $accountId)
                ->where('session_id', $request->session()->getId())->whereNull('revoked_at')
                ->update(['last_human_activity' => now(), 'updated_at' => now()]);
        }
    }

    public function confirmRecentAuthentication(Request $request): void
    {
        $request->session()->put('identity_recent_auth_at', now()->timestamp);
    }

    public function recentlyAuthenticated(Request $request): bool
    {
        $at = (int) $request->session()->get('identity_recent_auth_at', 0);

        return $at > 0 && now()->timestamp - $at < $this->for('admin')['recent_auth_minutes'] * 60;
    }

    public function publicContract(string $realm): array
    {
        $policy = $this->for($realm);

        return [
            'realm' => $realm,
            'inactivity_minutes' => $policy['inactivity_minutes'],
            'warning_minutes' => $realm === 'admin' ? $policy['warning_minutes'] : null,
            'remember_allowed' => $realm === 'customer',
            'remember_max_days' => $realm === 'customer' ? intdiv($policy['remember_minutes'], 1440) : null,
            'expire_on_close_without_remember' => true,
            'recent_auth_minutes' => $policy['recent_auth_minutes'],
        ];
    }

    private function isHumanActivity(Request $request): bool
    {
        if ($request->headers->get('X-MobiST-Background') === '1') {
            return false;
        }

        return ! $request->isMethodSafe() || $request->headers->get('X-MobiST-User-Activity') === '1';
    }

    private function for(string $realm): array
    {
        if (! in_array($realm, ['admin', 'customer'], true)) {
            throw new \InvalidArgumentException('Unknown identity realm.');
        }

        return config('identity.sessions.'.$realm);
    }
}
