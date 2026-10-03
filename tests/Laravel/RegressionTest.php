<?php

namespace Ninex\Lib\Tests;

use Illuminate\Support\Facades\DB;
use Ninex\Lib\Tests\Fixtures\{Item, ItemService};
use Ninex\Lib\Exceptions\ServiceException;

class RegressionTest extends TestCase
{
    public function testBatchUpdateBindsValuesAndUsesModelConnection(): void
    {
        $model = new class () extends Item {
            protected $connection = 'secondary';
        };
        $item = $model::create(['name' => 'original']);
        $value = "O'Reilly', status=999 --";
        $this->assertSame(1, $model::batchUpdate([['id' => $item->id, 'name' => $value]], 'id'));
        $this->assertSame($value, $model::find($item->id)->name);
        $this->assertSame(0, $model::find($item->id)->status);
        $this->assertSame(0, Item::count());
    }

    public function testUnsafeIdentifiersAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Item::batchUpdate([['id' => 1, 'name; DROP TABLE items' => 'x']], 'id');
    }

    public function testValidationRunsOnceAndZeroFiltersAreRetained(): void
    {
        $service = new ItemService();
        $item = $service->validateStore(['name' => 'a', 'status' => 0]);
        $service->store(['name' => 'b', 'status' => 1]);
        $this->assertSame(1, $service->validations);
        $this->assertSame(1, $service->paginate(['status' => 0])->total());
        $this->assertSame(1, $service->paginate(['status' => '0'])->total());
        $this->assertSame('c', $service->update((string) $item->id, ['name' => 'c'])->name);
        $this->assertSame(1, $service->validations);
        $service->validateUpdate((string) $item->id, ['name' => 'validated']);
        $this->assertSame(2, $service->validations);
    }

    public function testExplicitModelFillableIsStillHonored(): void
    {
        $item = (new ItemService())->store(['id' => 999, 'name' => 'allowed', 'is_admin' => true]);
        $this->assertNotSame(999, $item->id);
        $this->assertSame('allowed', $item->name);
        $this->assertArrayNotHasKey('is_admin', $item->getAttributes());
    }

    public function testInvalidDataAndPostSaveFailureRollBack(): void
    {
        try {
            (new ItemService())->validateStore([]);
            $this->fail('Expected validation error');
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $service = new class () extends ItemService {
            public function saved($model, $isEdit = false)
            {
                throw new \RuntimeException('hook');
            }
        };
        try {
            $service->store(['name' => 'x']);
            $this->fail('Expected hook error');
        } catch (\RuntimeException $e) {
            $this->assertSame('hook', $e->getMessage());
        }
        $this->assertSame(0, Item::count());
    }

    public function testArrayDateRangeAndMissingMessage(): void
    {
        $service = new ItemService();
        $query = $service->query();
        $service->scopeWhereBetween($query, ['created_at' => ['2020-01-01', '2020-02-01']]);
        $this->assertSame(['2020-01-01 00:00:00', '2020-02-01 23:59:59'], $query->getBindings());
        try {
            $service->show('999');
            $this->fail('Expected missing record');
        } catch (ServiceException $e) {
            $this->assertSame('数据不存在', $e->getMessage());
        }
    }

    public function testCacheMinutesAndInvalidationAfterCommit(): void
    {
        $service = new ItemService();
        $calls = 0;
        $read = function () use (&$calls) {
            return ++$calls;
        };
        $this->assertSame(1, $service->cached($read));
        $this->travel(61)->seconds();
        $this->assertSame(1, $service->cached($read));
        $this->travel(3600)->seconds();
        $this->assertSame(2, $service->cached($read));
        config(['ninexlib.model_cache.enabled' => true]);
        $item = Item::create(['name' => 'old']);
        $this->assertSame('old', Item::findWithCache($item->id)->name);
        DB::beginTransaction();
        $item->update(['name' => 'new']);
        $this->assertSame('new', Item::findWithCache($item->id)->name);
        DB::rollBack();
        $this->assertSame('old', Item::findWithCache($item->id)->name);
        $item->refresh()->update(['name' => 'committed']);
        $this->assertSame('committed', Item::findWithCache($item->id)->name);
    }

    public function testJobCanBeConstructedWithDefaultQueue(): void
    {
        $job = new class () extends \Ninex\Lib\Jobs\LibJob {
            protected function execute()
            {
                return 1;
            }
        };
        $this->assertSame('default', $job->queue);
        $this->assertSame(3, $job->tries);
    }
    public function testSimplePaginationDoesNotInventTotals(): void
    {
        Item::create(['name' => 'one']);
        Item::create(['name' => 'two']);
        $paginator = Item::paginateWithoutTotal([], ['id' => 'asc'], 1);
        $presenter = new class () {
            use \Ninex\Lib\Http\Traits\ResponseTrait;
            public function emit($data)
            {
                return $this->success($data)->getData(true);
            }
        };
        $data = $presenter->emit($paginator)['data'];
        $this->assertTrue($data['has_more']);
        $this->assertArrayNotHasKey('total', $data);
        $this->assertArrayNotHasKey('total_pages', $data);
    }

}
