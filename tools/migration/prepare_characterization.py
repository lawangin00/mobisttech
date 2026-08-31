"""Prepare only ignored pinned exports; never run against a source checkout."""
from pathlib import Path
import base64

ROOT = Path(__file__).resolve().parents[2]
for label in ('pos', 'website'):
    target = ROOT / '.local/mt11/sources' / label
    assert target.resolve().is_relative_to((ROOT / '.local').resolve())
    assert not (target / '.git').exists()
    for directory in ('bootstrap/cache', 'storage/framework/cache/data',
                      'storage/framework/sessions', 'storage/framework/views',
                      'storage/logs', 'storage/app/private', 'storage/app/public'):
        (target / directory).mkdir(parents=True, exist_ok=True)
    env = {
        'APP_ENV': 'testing', 'APP_KEY': 'base64:' + base64.b64encode(b'mt11-synthetic-test-key-only-000').decode(),
        'APP_URL': 'http://localhost', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:', 'DB_URL': '',
        'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array', 'MAIL_MAILER': 'array',
        'QUEUE_CONNECTION': 'sync', 'BROADCAST_CONNECTION': 'null',
        'BACKUP_REMOTE_ENABLED': 'false', 'POS_CATALOG_SYNC_ENABLED': 'false',
        'POS_ORDER_SYNC_ENABLED': 'false', 'POS_ORDER_API_TOKEN': '',
        'WEBSITE_ORDER_API_ENABLED': 'false', 'WEBSITE_ORDER_API_TOKEN': '',
        'EASYPAISA_ENABLED': 'false', 'JAZZCASH_ENABLED': 'false', 'CARD_PAYMENTS_ENABLED': 'false',
    }
    (target / '.env').write_text(''.join(f'{k}={v}\n' for k, v in env.items()))
    (target / 'tests/TestCase.php').write_text('''<?php
namespace Tests;
use Illuminate\\Foundation\\Testing\\TestCase as BaseTestCase;
use Illuminate\\Support\\Facades\\Http;
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || realpath(base_path()) !== realpath(dirname(__DIR__))) {
            throw new \\RuntimeException('Characterization isolation guard failed');
        }
        Http::preventStrayRequests();
        $this->withoutVite();
    }
}
''')
    print(label + ': synthetic environment, local storage, stray-HTTP guard, Vite bypass prepared')
