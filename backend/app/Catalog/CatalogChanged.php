<?php

namespace App\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogChanged
{
    // Called inside the owning write transaction; no external effect before commit.
    public static function record(string $type, string $id, int $version): void
    {
        DB::table('publication_versions')->insertOrIgnore(['domain' => 'catalogue', 'version' => 0]);
        DB::table('publication_versions')->where('domain', 'catalogue')->increment('version');
        DB::table('domain_events')->insert(['id' => (string) Str::uuid(), 'aggregate_type' => $type, 'aggregate_id' => $id,
            'aggregate_version' => $version, 'event_type' => 'catalogue.changed', 'payload' => json_encode(['id' => $id]),
            'operation_key' => (string) Str::uuid(), 'created_at' => now()]);
    }
}
