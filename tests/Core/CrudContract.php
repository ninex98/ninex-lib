<?php

namespace Ninex\Lib\Core\Tests;

use Ninex\Lib\Core\CrudRepository;
use Ninex\Lib\Core\CrudService;
use Ninex\Lib\Core\ServiceException;

/** The identical contract is exercised against Eloquent and ThinkORM repositories. */
trait CrudContract
{
    abstract protected function repository(array $readable = ['id', 'name', 'status'], bool $onlyInactive = false): CrudRepository;
    abstract protected function insertOutsideScope(): int;

    protected function service(?\Closure $authorize = null): CrudService
    {
        return new CrudService($this->repository(), ['name', 'status'], ['status'], ['id', 'name'], function ($operation, $data) {
            if ($operation === 'store' && empty($data['name'])) {
                throw new ServiceException('name required', 422, ['errors' => ['name' => ['required']]]);
            }
            return $data;
        }, $authorize ?? fn () => true, 20);
    }

    private function assertBusinessError(int $status, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected business exception.');
        } catch (ServiceException $e) {
            $this->assertSame($status, $e->getHttpStatus());
        }
    }

    public function testCrudRoundTripAndOutputWhitelist(): void
    {
        $service = $this->service();
        $item = $service->store(['name' => "O'Reilly 中文", 'status' => 0]);
        $this->assertSame("O'Reilly 中文", $service->show($item['id'])['name']);
        $this->assertArrayNotHasKey('tenant_id', $item);
        $this->assertSame('changed', $service->update($item['id'], ['name' => 'changed'])['name']);
        $service->destroy($item['id']);
        $this->assertBusinessError(404, fn () => $service->show($item['id']));
    }

    public function testFilteringZeroSortingAndPagination(): void
    {
        $service = $this->service();
        foreach ([['a', 0], ['b', 1], ['c', 0]] as [$name, $status]) {
            $service->store(compact('name', 'status'));
        }
        $page = $service->paginate(['filter' => ['status' => '0'], 'sort' => '-name', 'page_size' => 1, 'page' => 2]);
        $this->assertSame(2, $page->total);
        $this->assertSame('a', $page->items[0]['name']);
        $this->assertSame(2, $page->toArray()['total_pages']);
    }

    public function testRejectsUnsafeFieldsAndInvalidQueries(): void
    {
        $service = $this->service();
        $this->assertBusinessError(422, fn () => $service->store(['name' => 'x', 'tenant_id' => 2]));
        $this->assertBusinessError(422, fn () => $service->store([]));
        foreach ([['filter' => ['tenant_id' => 2]], ['sort' => 'secret'], ['page_size' => 21], ['page' => 0], ['page' => []], ['filter' => ['status' => [0, 1]]]] as $query) {
            $this->assertBusinessError(422, fn () => $service->paginate($query));
        }
        $this->assertSame(0, $service->paginate()->total);
    }

    public function testAuthorizationAppliesToEveryOperation(): void
    {
        $item = $this->service()->store(['name' => 'original', 'status' => 0]);
        $denied = $this->service(fn () => false);
        foreach ([fn () => $denied->paginate(), fn () => $denied->show($item['id']), fn () => $denied->store(['name' => 'x']), fn () => $denied->update($item['id'], ['name' => 'changed']), fn () => $denied->destroy($item['id'])] as $operation) {
            $this->assertBusinessError(403, $operation);
        }
        $this->assertSame('original', $this->service()->show($item['id'])['name']);
    }

    public function testTenantScopeAppliesToReadUpdateAndDelete(): void
    {
        $id = $this->insertOutsideScope();
        $service = $this->service();
        $this->assertSame(0, $service->paginate()->total);
        $this->assertBusinessError(404, fn () => $service->show($id));
        $this->assertBusinessError(404, fn () => $service->update($id, ['name' => 'changed']));
        $this->assertBusinessError(404, fn () => $service->destroy($id));
    }

    public function testRepositoryRollsBackAllWritesOnFailure(): void
    {
        $repo = $this->repository();
        try {
            $repo->transaction(function () use ($repo) {
                $repo->create(['name' => 'rollback', 'status' => 0]);
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('fail', $e->getMessage());
        }
        $this->assertSame(0, $this->service()->paginate()->total);
    }
    public function testUpdateCannotMoveRecordOutsideItsScope(): void
    {
        $record = $this->service()->store(['name' => 'scoped', 'status' => 0]);
        $restricted = new CrudService($this->repository(onlyInactive: true), ['name', 'status'], [], [], fn ($op, $data) => $data, fn () => true);
        $this->assertBusinessError(404, fn () => $restricted->update($record['id'], ['status' => 1]));
        $this->assertEquals(0, $this->service()->show($record['id'])['status']);
    }

    public function testEmptyOutputWhitelistDoesNotMakeExistingRecordMissing(): void
    {
        $record = $this->service()->store(['name' => 'private fields', 'status' => 0]);
        $service = new CrudService($this->repository(readable: []), ['name'], [], [], fn ($op, $data) => $data, fn () => true);
        $this->assertSame([], $service->show($record['id']));
        $this->assertSame([], $service->update($record['id'], ['name' => 'updated']));
        $this->assertSame('updated', $this->service()->show($record['id'])['name']);
    }

}
