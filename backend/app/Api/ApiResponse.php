<?php

namespace App\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    public function public(Request $request, array $data, string $contract, int $maxAge = 60): Response
    {
        $payload = ['contract' => $contract, 'data' => $data];
        $etag = '"'.hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)).'"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age='.$maxAge.', stale-while-revalidate=30');
        }

        return response()->json($payload)->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age='.$maxAge.', stale-while-revalidate=30')
            ->header('Vary', 'Accept-Encoding');
    }

    public function private(array $data, string $contract = 'mobisttech-api.v1', int $status = 200): JsonResponse
    {
        return response()->json(['contract' => $contract, 'data' => $data], $status)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
