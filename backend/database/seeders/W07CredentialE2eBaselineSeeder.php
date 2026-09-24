<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class W07CredentialE2eBaselineSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        abort_unless(DB::table('site_secret_settings')->where('key', 'payments.card.api_secret')->doesntExist(), 409,
            'W07 browser credential baseline must not contain existing provider configuration.');
    }
}
