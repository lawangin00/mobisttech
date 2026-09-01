<?php

namespace App\Integrations;

use App\Business\BusinessProfile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleOAuthClient
{
    public function __construct(private BusinessProfile $businessProfile) {}

    public function authorizationUrl(string $provider, string $state, string $challenge): string
    {
        $config = $this->config($provider);
        $query = ['client_id' => $config['client_id'], 'redirect_uri' => $config['redirect'], 'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']), 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'false',
            'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
        $query['login_hint'] = $this->businessProfile->current()['business_email'];

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $provider, string $code, string $verifier): array
    {
        $config = $this->config($provider);
        $response = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'], 'code' => $code,
            'code_verifier' => $verifier, 'grant_type' => 'authorization_code', 'redirect_uri' => $config['redirect'],
        ])->throw()->json();

        return $this->tokens($response);
    }

    public function refresh(string $provider, string $refreshToken): array
    {
        $config = $this->config($provider);
        $response = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
            'refresh_token' => $refreshToken, 'grant_type' => 'refresh_token',
        ])->throw()->json();

        return $this->tokens($response) + ['refresh_token' => $refreshToken];
    }

    public function gmailAccount(string $accessToken): string
    {
        return strtolower((string) Http::withToken($accessToken)->acceptJson()->timeout(15)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')->throw()->json('emailAddress'));
    }

    public function driveAccount(string $accessToken): string
    {
        return strtolower((string) Http::withToken($accessToken)->acceptJson()->timeout(15)
            ->get('https://www.googleapis.com/drive/v3/about', ['fields' => 'user(emailAddress)'])->throw()->json('user.emailAddress'));
    }

    public function config(string $provider): array
    {
        $config = config('services.google.'.$provider);
        if (! config('services.google.enabled') || ! in_array($provider, ['gmail', 'google_drive'], true)
            || ! is_array($config) || trim((string) ($config['client_id'] ?? '')) === '' || trim((string) ($config['client_secret'] ?? '')) === '') {
            throw new RuntimeException('Google OAuth application configuration is unavailable.');
        }

        return $config;
    }

    private function tokens(array $response): array
    {
        if (! is_string($response['access_token'] ?? null) || ! is_numeric($response['expires_in'] ?? null)) {
            throw new RuntimeException('Google returned an invalid token response.');
        }

        return array_filter(['access_token' => $response['access_token'], 'refresh_token' => $response['refresh_token'] ?? null,
            'token_type' => $response['token_type'] ?? 'Bearer', 'scope' => $response['scope'] ?? '',
            'expires_at' => now()->addSeconds(max(60, (int) $response['expires_in'] - 30))->format('Y-m-d H:i:s.u')], fn ($value) => $value !== null);
    }
}
