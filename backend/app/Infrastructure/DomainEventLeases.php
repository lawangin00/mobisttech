<?php

namespace App\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DomainEventLeases
{
    public function claim(string $consumer, int $leaseSeconds = 30): ?array
    {
        $this->consumer($consumer);
        abort_unless($leaseSeconds >= 5 && $leaseSeconds <= 300, 422, 'Invalid event lease duration.');

        return DB::transaction(function () use ($consumer, $leaseSeconds) {
            $now = now();
            $row = DB::table('domain_events')
                ->whereNull('completed_at')->whereNull('failed_at')
                ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
                ->where(fn ($query) => $query->whereNull('lease_until')->orWhere('lease_until', '<=', $now))
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            $token = hash('sha256', $consumer.'|'.Str::uuid());
            DB::table('domain_events')->where('id', $row->id)->update([
                'lease_token' => $token,
                'lease_until' => $now->copy()->addSeconds($leaseSeconds),
                'attempts' => DB::raw('attempts + 1'),
            ]);

            return $this->payload(DB::table('domain_events')->where('id', $row->id)->firstOrFail());
        });
    }

    public function complete(string $id, string $token): void
    {
        DB::transaction(function () use ($id, $token) {
            $row = $this->owned($id, $token);
            DB::table('domain_events')->where('id', $row->id)->update([
                'completed_at' => now(), 'lease_token' => null, 'lease_until' => null,
                'next_attempt_at' => null, 'last_error_code' => null,
            ]);
        });
    }

    public function fail(string $id, string $token, string $errorCode, int $maxAttempts = 3, int $backoffSeconds = 5): void
    {
        if (! preg_match('/\A[A-Z0-9_.-]{1,80}\z/', $errorCode)) {
            throw ValidationException::withMessages(['error_code' => 'Invalid event error code.']);
        }
        abort_unless($maxAttempts >= 1 && $maxAttempts <= 20 && $backoffSeconds >= 0 && $backoffSeconds <= 3600, 422);

        DB::transaction(function () use ($id, $token, $errorCode, $maxAttempts, $backoffSeconds) {
            $row = $this->owned($id, $token);
            $terminal = (int) $row->attempts >= $maxAttempts;
            DB::table('domain_events')->where('id', $row->id)->update([
                'lease_token' => null,
                'lease_until' => null,
                'last_error_code' => $errorCode,
                'next_attempt_at' => $terminal ? null : now()->addSeconds($backoffSeconds),
                'failed_at' => $terminal ? now() : null,
            ]);
        });
    }

    private function owned(string $id, string $token): object
    {
        $row = DB::table('domain_events')->where('id', $id)->lockForUpdate()->firstOrFail();
        abort_unless($row->completed_at === null && $row->failed_at === null, 409, 'Event is already terminal.');
        abort_unless(is_string($row->lease_token) && hash_equals($row->lease_token, $token), 409, 'Event lease is stale.');

        return $row;
    }

    private function consumer(string $consumer): void
    {
        if (! preg_match('/\A[a-z0-9._:-]{3,80}\z/', $consumer)) {
            throw ValidationException::withMessages(['consumer' => 'Invalid event consumer.']);
        }
    }

    private function payload(object $row): array
    {
        return [
            'id' => $row->id,
            'event_type' => $row->event_type,
            'aggregate_type' => $row->aggregate_type,
            'aggregate_id' => $row->aggregate_id,
            'aggregate_version' => (int) $row->aggregate_version,
            'payload' => json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR),
            'attempts' => (int) $row->attempts,
            'lease_token' => $row->lease_token,
            'lease_until' => $row->lease_until,
        ];
    }
}
