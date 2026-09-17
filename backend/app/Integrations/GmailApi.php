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

    public function send(string $to, string $subject, string $text, string $html, bool $allowConnecting = false): ?string
    {
        return $this->deliver($to, $subject, $text, $html, null, null, $allowConnecting);
    }

    public function sendDocument(string $to, string $subject, string $text, string $html, string $filename, string $pdf): ?string
    {
        if (! str_starts_with($pdf, '%PDF-') || strpbrk($filename, "\r\n\"\\") !== false || ! str_ends_with(strtolower($filename), '.pdf')) {
            throw new RuntimeException('Invalid PDF attachment.');
        }

        return $this->deliver($to, $subject, $text, $html, $filename, $pdf, false);
    }

    private function deliver(string $to, string $subject, string $text, string $html, ?string $filename, ?string $pdf, bool $allowConnecting): ?string
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
        $mime = $this->mime($business, $to, $subject, $text, $html, $filename, $pdf);
        $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
        $response = Http::withToken($tokens['access_token'])->acceptJson()->timeout(20)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw])->throw();
        DB::table('integration_connections')->where('id', $connection->id)->update([
            'last_success_at' => now(), 'last_error_summary' => null, 'updated_at' => now(),
        ]);

        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function mime(array $business, string $to, string $subject, string $text, string $html, ?string $filename, ?string $pdf): string
    {
        $alternate = 'mobisttech_alt_'.bin2hex(random_bytes(12));
        $headers = [
            'From: '.$business['business_name'].' <'.$business['business_email'].'>', 'Reply-To: '.$business['business_email'],
            'To: '.$to, 'Subject: '.$subject, 'MIME-Version: 1.0',
        ];
        $alternative = [
            '--'.$alternate, 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', '', $text, '',
            '--'.$alternate, 'Content-Type: text/html; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', '', $html, '', '--'.$alternate.'--',
        ];
        if ($filename === null || $pdf === null) {
            return implode("\r\n", [...$headers, 'Content-Type: multipart/alternative; boundary="'.$alternate.'"', '', ...$alternative, '']);
        }

        $mixed = 'mobisttech_mix_'.bin2hex(random_bytes(12));

        return implode("\r\n", [
            ...$headers, 'Content-Type: multipart/mixed; boundary="'.$mixed.'"', '', '--'.$mixed,
            'Content-Type: multipart/alternative; boundary="'.$alternate.'"', '', ...$alternative, '', '--'.$mixed,
            'Content-Type: application/pdf; name="'.$filename.'"', 'Content-Transfer-Encoding: base64',
            'Content-Disposition: attachment; filename="'.$filename.'"', '', rtrim(chunk_split(base64_encode($pdf), 76, "\r\n")),
            '--'.$mixed.'--', '',
        ]);
    }
}
