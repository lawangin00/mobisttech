<?php

namespace Database\Seeders;

use App\Identity\FirstAdminProvisioning;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FirstOutletE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing')
            && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_FIRST_OUTLET_E2E_ENABLED') === '1', 403);

        app(FirstAdminProvisioning::class)->create(
            'MT75 Fresh Protected Owner',
            'mt75-fresh-owner@example.invalid',
            'SyntheticFreshOwner123!',
        );
    }
}
