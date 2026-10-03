<?php

namespace Ninex\Lib\Core\Tests;

use PDO;
use RuntimeException;

/** Test-only fixture. MySQL schemas are created with unique names and only those names are dropped. */
final class DatabaseFixture
{
    private ?PDO $admin = null;
    private array $databases = [];

    public function driver(): string
    {
        $driver = getenv('NINEX_TEST_DB_DRIVER') ?: 'sqlite';
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Unsupported test database driver: '.$driver);
        }
        return $driver;
    }

    private function mysqlDsn(): string
    {
        $socket = getenv('NINEX_TEST_MYSQL_SOCKET');
        return $socket ? 'mysql:unix_socket='.$socket : 'mysql:host='.(getenv('NINEX_TEST_MYSQL_HOST') ?: '127.0.0.1').';port='.(getenv('NINEX_TEST_MYSQL_PORT') ?: '3306');
    }

    private function database(string $key): string
    {
        if (!isset($this->databases[$key])) {
            $this->admin ??= new PDO($this->mysqlDsn(), getenv('NINEX_TEST_MYSQL_USER') ?: 'root', getenv('NINEX_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $name = 'ninex_test_'.bin2hex(random_bytes(12));
            $this->admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->databases[$key] = $name;
        }
        return $this->databases[$key];
    }

    public function laravel(string $key): array
    {
        if ($this->driver() === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }
        return [
            'driver' => 'mysql', 'database' => $this->database($key),
            'host' => getenv('NINEX_TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('NINEX_TEST_MYSQL_PORT') ?: '3306',
            'unix_socket' => getenv('NINEX_TEST_MYSQL_SOCKET') ?: '',
            'username' => getenv('NINEX_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('NINEX_TEST_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => true, 'engine' => 'InnoDB',
        ];
    }

    public function think(): array
    {
        $connection = ['type' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        if ($this->driver() === 'mysql') {
            $database = $this->database('think');
            $connection = [
                'type' => 'mysql', 'database' => $database,
                'dsn' => $this->mysqlDsn().';dbname='.$database.';charset=utf8mb4',
                'username' => getenv('NINEX_TEST_MYSQL_USER') ?: 'root',
                'password' => getenv('NINEX_TEST_MYSQL_PASSWORD') ?: '',
                'charset' => 'utf8mb4', 'prefix' => '',
            ];
        }
        return ['default' => 'testing', 'connections' => ['testing' => $connection]];
    }

    public function createTableSql(string $table): string
    {
        if (!in_array($table, ['items', 'products'], true)) {
            throw new RuntimeException('Unknown test fixture table.');
        }
        $id = $this->driver() === 'mysql' ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $engine = $this->driver() === 'mysql' ? ' ENGINE=InnoDB' : '';
        return 'CREATE TABLE '.$table.' (id '.$id.', name VARCHAR(255) NOT NULL, status INTEGER DEFAULT 0, tenant_id INTEGER DEFAULT 1)'.$engine;
    }

    public function close(): void
    {
        foreach ($this->databases as $key => $name) {
            $this->admin->exec('DROP DATABASE `'.$name.'`');
            unset($this->databases[$key]);
        }
        $this->admin = null;
    }
}
