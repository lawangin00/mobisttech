<?php

namespace App\Integrations;

use App\Business\BusinessProfile;

final class GmailRecovery
{
    public function __construct(private GmailApi $gmail) {}

    public function send(object $account, string $realm, string $token): void
    {
        $business = app(BusinessProfile::class)->current();
        $origin = $realm === 'customer' ? config('identity.customer_origin') : config('identity.admin_origin');
        $path = $realm === 'customer' ? '/reset-password' : '/internal/admin/reset-password';
        $url = $origin.$path.'?'.http_build_query(['token' => $token, 'email' => $account->email]);
        if (! str_starts_with($url, 'https://') && app()->environment('production')) {
            throw new \RuntimeException('Production recovery URLs require HTTPS.');
        }
        $subject = 'Reset your '.$business['business_name'].' password';
        $text = "Use this secure single-use link within 60 minutes:\n".$url."\n\nIf you did not request this, no action is needed.";
        $html = '<p>Use this secure single-use link within 60 minutes:</p><p><a href="'.e($url).'">Reset Password</a></p><p>If you did not request this, no action is needed.</p>';
        $this->gmail->send($account->email, $subject, $text, $html);
    }
}
