<?php

namespace App\Identity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Adapted from the POS AccountSessionService; account-row locking closes concurrent device-limit races.
final class PosSessions
{
    public function register(Request $request, string $guard, int $accountId): ?string
    {
        if (! in_array($guard, ['admin', 'superadmin'], true)) {
            throw new \InvalidArgumentException('POS session guard required.');
        }

        return DB::transaction(function () use ($request, $guard, $accountId) {
            $table = $guard === 'admin' ? 'admins' : 'super_admins';
            DB::table($table)->where('id', $accountId)->lockForUpdate()->firstOrFail();
            $device = $this->deviceId($request);
            $others = $this->active($guard, $accountId)->where('device_id', '!=', $device)->get();
            if ($others->contains(fn ($row) => $row->location_key !== $this->locationKey($request))
                || $others->count() >= 2
                || $others->contains(fn ($row) => $row->device_type === $this->deviceType($request))) {
                return 'Device or network limit reached.';
            }
            DB::table('account_sessions')->updateOrInsert(
                ['guard' => $guard, 'account_id' => $accountId, 'device_id' => $device],
                ['device_type' => $this->deviceType($request), 'location_key' => $this->locationKey($request),
                    'ip_address' => $request->ip(), 'session_id' => $request->session()->getId(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                    'last_activity' => now(), 'created_at' => now(), 'updated_at' => now(), 'revoked_at' => null]
            );

            return null;
        });
    }

    public function validateCurrent(Request $request, string $guard, int $accountId): ?string
    {
        $record = $this->active($guard, $accountId)->where('device_id', $this->deviceId($request))->first();
        if (! $record || ! hash_equals($record->session_id, $request->session()->getId())) {
            return 'Session is expired, replaced or revoked.';
        }
        if ($record->location_key !== $this->locationKey($request)
            && $this->active($guard, $accountId)->where('id', '!=', $record->id)->exists()) {
            $this->release($request, $guard, $accountId);

            return 'Network changed while another device is active.';
        }
        DB::table('account_sessions')->where('id', $record->id)->update([
            'location_key' => $this->locationKey($request), 'ip_address' => $request->ip(),
            'last_activity' => now(), 'updated_at' => now(),
        ]);

        return null;
    }

    public function release(Request $request, string $guard, int $accountId): void
    {
        DB::table('account_sessions')->where('guard', $guard)->where('account_id', $accountId)
            ->where('session_id', $request->session()->getId())->update(['revoked_at' => now()]);
    }

    private function active(string $guard, int $accountId)
    {
        return DB::table('account_sessions')->where('guard', $guard)->where('account_id', $accountId)
            ->whereNull('revoked_at')->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 30)));
    }

    private function deviceId(Request $request): string
    {
        $runtimeDeviceId = $request->attributes->get('mobist_device_runtime');
        if (is_string($runtimeDeviceId) && preg_match('/^[a-f0-9]{64}$/', $runtimeDeviceId)) {
            return $runtimeDeviceId;
        }

        $deviceId = $request->cookie('mobist_device_'.$request->attributes->get('identity_realm'));
        if (is_string($deviceId) && preg_match('/^[a-f0-9]{64}$/', $deviceId)) {
            $request->attributes->set('mobist_device_runtime', $deviceId);

            return $deviceId;
        }

        $deviceId = bin2hex(random_bytes(32));
        $request->attributes->set('mobist_device_runtime', $deviceId);

        Cookie::queue(cookie(
            'mobist_device_'.$request->attributes->get('identity_realm'),
            $deviceId,
            525600,
            config('session.path'),
            null,
            (bool) config('session.secure'),
            true,
            false,
            'Lax'
        ));

        return $deviceId;
    }

    private function deviceType(Request $request): string
    {
        $agent = strtolower((string) $request->userAgent());

        return preg_match('/android|iphone|ipad|ipod|mobile|tablet/', $agent)
            ? 'mobile'
            : 'desktop';
    }

    private function locationKey(Request $request): string
    {
        $ip = (string) ($request->ip() ?: 'unknown');

        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'local-network';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long !== false) {
                $unsigned = (int) sprintf('%u', $long);
                $private = (
                    ($unsigned >= 167772160 && $unsigned <= 184549375) ||
                    ($unsigned >= 2886729728 && $unsigned <= 2887778303) ||
                    ($unsigned >= 3232235520 && $unsigned <= 3232301055)
                );

                if ($private) {
                    return 'local-network';
                }
            }

            return 'ipv4:'.$ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                $bytes = unpack('n4', substr($packed, 0, 8));
                $prefix = implode(':', array_map(fn ($value) => dechex($value), $bytes ?: []));

                return 'ipv6-64:'.$prefix;
            }
        }

        return 'unknown:'.hash('sha256', $ip);
    }
}
