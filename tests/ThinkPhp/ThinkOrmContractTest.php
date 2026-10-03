<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Core\CrudRepository;
use Ninex\Lib\Core\Tests\CrudContract;
use Ninex\Lib\ThinkPhp\ThinkOrmRepository;
use think\DbManager;

class ThinkOrmContractTest extends TestCase
{
    use CrudContract;
    private DbManager $db;
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new DbManager();
        $this->db->setConfig($this->databaseFixture->think());
        $this->db->connect()->execute($this->databaseFixture->createTableSql('items'));
    }
    protected function repository(array $readable = ['id', 'name', 'status'], bool $onlyInactive = false): CrudRepository
    {
        return new ThinkOrmRepository($this->db, 'items', $readable, 'id', function ($q) use ($onlyInactive) {
            $q->where('tenant_id', 1);
            if ($onlyInactive) {
                $q->where('status', 0);
            }
        }, ['tenant_id' => 1]);
    }
    protected function insertOutsideScope(): int
    {
        return $this->db->table('items')->insertGetId(['name' => 'private', 'tenant_id' => 2]);
    }

    public function testNoLaravelOrSymfonyFrameworkIsInstalled(): void
    {
        $this->assertFalse(class_exists(\Illuminate\Foundation\Application::class));
        $this->assertFalse(class_exists(\Symfony\Component\HttpKernel\Kernel::class));
    }
}
