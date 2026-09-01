<?php

namespace App\Integrations;

use App\Business\BusinessProfile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GmailApi
{
    public function __construct(private GoogleOAuthClient $oauth) {}

    public function send(string $to, string $subject, string $text, string $html, bool $allowConnecting = false): void
    {
        foreach ([$to, $subject] as $header) {
            if (preg_match('/[\r\n]/', $header)) {
                throw new RuntimeException('Invalid email header.');
            }
        }
        $connection = DB::table('integration_connections')->where('provider', 'gmail')->lockForUpdate()->firstOrFail();
        if ($connection->status !== 'connected' && ! ($allowConnecting && $connection->status === 'connecting')) {
            throw new RuntimeException('Gmail is not connected.');
        }
        $tokens = json_decode(Crypt::decryptString($connection->encrypted_credentials), true, flags: JSON_THROW_ON_ERROR);
        if (now()->gte($tokens['expires_at'])) {
            if (! is_string($tokens['refresh_token'] ?? null)) {
                throw new RuntimeException('Gmail authorization requires reconnection.');
            }
            $tokens = $this->oauth->refresh('gmail', $tokens['refresh_token']);
            DB::table('integration_connections')->where('id', $connection->id)->update([
                'encrypted_credentials' => Crypt::encryptString(json_encode($tokens, JSON_THROW_ON_ERROR)), 'updated_at' => now(),
            ]);
        }
        $business = app(BusinessProfile::class)->current();
        $boundary = 'mobisttech_'.bin2hex(random_bytes(12));
        $mime = implode("\r\n", [
            'From: '.$business['business_name'].' <'.$business['business_email'].'>', 'Reply-To: '.$business['business_email'], 'To: '.$to,
            'Subject: '.$subject, 'MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="'.$boundary.'"', '',
            '--'.$boundary, 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', '', $text, '',
            '--'.$boundary, 'Content-Type: text/html; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', '', $html, '', '--'.$boundary.'--', '',
        ]);
        $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
        Http::withToken($tokens['access_token'])->acceptJson()->timeout(20)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw])->throw();
        DB::table('integration_connections')->where('id', $connection->id)->update(['last_success_at' => now(), 'last_error_summary' => null, 'updated_at' => now()]);
    }
}
