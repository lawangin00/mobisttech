<?php

namespace Tests\Unit;

use App\Support\LocalEnvironmentGuard;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalEnvironmentGuardTest extends TestCase
{
    private const DATABASE = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 13306,
        'database' => 'mobisttech_test', 'username' => 'mobisttech'];

    public function test_isolated_target_is_accepted(): void
    {
        LocalEnvironmentGuard::validate(self::DATABASE, ['host' => '127.0.0.1', 'port' => 16379], false);
        $this->addToAssertionCount(1);
    }

    public static function unsafeDatabases(): array
    {
        return [
            'source port' => [['port' => 3306]],
            'remote host' => [['host' => 'example.invalid']],
            'source database' => [['database' => 'mobist_pos']],
            'root account' => [['username' => 'root']],
            'URL override' => [['url' => 'mysql://example.invalid/unsafe']],
            'SQLite source path' => [['driver' => 'sqlite']],
            'socket override' => [['unix_socket' => '/tmp/source.sock']],
        ];
    }

    #[DataProvider('unsafeDatabases')]
    public function test_unsafe_database_configuration_fails_before_connection(array $changes): void
    {
        $this->expectException(LogicException::class);
        LocalEnvironmentGuard::validate(array_replace(self::DATABASE, $changes), ['host' => '127.0.0.1', 'port' => 16379], false);
    }

    public function test_external_integrations_cannot_be_enabled_locally(): void
    {
        $this->expectException(LogicException::class);
        LocalEnvironmentGuard::validate(self::DATABASE, ['host' => '127.0.0.1', 'port' => 16379], true);
    }
}
