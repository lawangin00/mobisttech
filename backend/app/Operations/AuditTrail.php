<?php

namespace App\Operations;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;

final class AuditTrail
{
    public function __construct(private AuditRedactor $redactor) {}

    public function admin(Admin $actor, string $action, string $method, string $path, array $payload = [], int $status = 200): void
    {
        DB::table('admin_audit_logs')->insert([
            'user_id' => null,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => mb_substr($action, 0, 180),
            'method' => strtoupper(mb_substr($method, 0, 10)),
            'path' => $this->redactor->path($path),
            'payload' => json_encode($this->redactor->payload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'ip_address' => null,
            'user_agent' => null,
            'status_code' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function system(string $action, array $payload = [], int $status = 200): void
    {
        DB::table('pos_audit_logs')->insert([
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'actor_email' => null,
            'outlet_id' => null,
            'action' => mb_substr($action, 0, 180),
            'method' => 'SYSTEM',
            'path' => '/',
            'payload' => json_encode($this->redactor->payload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'ip_address' => null,
            'user_agent' => null,
            'status_code' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
