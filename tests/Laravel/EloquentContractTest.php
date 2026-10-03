<?php

namespace Ninex\Lib\Tests;

use Ninex\Lib\Core\CrudRepository;
use Ninex\Lib\Core\Tests\CrudContract;
use Ninex\Lib\Database\EloquentRepository;
use Ninex\Lib\Tests\Fixtures\Item;

class EloquentContractTest extends TestCase
{
    use CrudContract;
    protected function repository(array $readable = ['id', 'name', 'status'], bool $onlyInactive = false): CrudRepository
    {
        return new EloquentRepository(Item::class, $readable, function ($q) use ($onlyInactive) {
            $q->where('tenant_id', 1);
            if ($onlyInactive) {
                $q->where('status', 0);
            }
        }, ['tenant_id' => 1]);
    }
    protected function insertOutsideScope(): int
    {
        return Item::create(['name' => 'private', 'tenant_id' => 2])->id;
    }
    public function testPaginationHydratesOnlyTheRequestedPageWithBoundedQueries(): void
    {
        for ($i = 0; $i < 20; $i++) {
            Item::create(['name' => 'item-'.$i, 'status' => 0]);
        }
        $retrieved = 0;
        Item::retrieved(function () use (&$retrieved) {
            $retrieved++;
        });
        $connection = (new Item())->getConnection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        $page = $this->service()->paginate(['page_size' => 5, 'page' => 2]);
        $this->assertSame(20, $page->total);
        $this->assertSame(5, $retrieved);
        $this->assertLessThanOrEqual(2, count($connection->getQueryLog()));
        $connection->disableQueryLog();
    }

}
