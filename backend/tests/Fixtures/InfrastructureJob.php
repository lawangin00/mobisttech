<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InfrastructureJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $domain, public bool $fail = false) {}

    public function handle(): void
    {
        if ($this->fail) {
            throw new RuntimeException('Synthetic infrastructure failure');
        }
        DB::table('publication_versions')->updateOrInsert(['domain' => $this->domain], ['version' => 1, 'updated_at' => now()]);
    }
}
