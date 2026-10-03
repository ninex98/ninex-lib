<?php

namespace Ninex\Lib\Core\Tests;

use Ninex\Lib\Contracts\CrudActions;
use Ninex\Lib\Core\ServiceException;

/** 两个框架都通过修改生成的 Service 验证扩展行为。 / Exercise customization of generated services in both frameworks. */
trait BusinessFlowContract
{
    abstract protected function businessService(): CrudActions;
    abstract protected function recordCount(): int;
    abstract protected function auditCount(): int;
    abstract protected function insertOutsideRecord(): void;

    public function testInstanceOperationsUseTheSameValidationAndHooks(): void
    {
        $service = $this->businessService();
        $record = $service->save(['form_name' => 'direct', 'status' => 0]);
        $this->assertSame(['validate', 'saving', 'saved'], $service->events);
        $this->assertSame('DIRECT!', $service->find($record['id'])['name']);
        $service->events = [];
        $this->assertSame('changed!', $service->save(['name' => 'changed'], $record['id'])['name']);
        $this->assertSame(['validate', 'saving', 'saved'], $service->events);
        $this->assertSame(1, $service->getPage()->total);
        $service->events = [];
        $service->delete($record['id']);
        $this->assertSame(['deleting', 'deleted'], $service->events);
        $this->assertSame(0, $this->recordCount());
        try {
            $service->create(['name' => 'spoof', 'status' => 0, 'owner_id' => 9]);
            $this->fail('Expected direct create validation');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->getHttpStatus());
        }
    }

    public function testLikeHelperBindsValuesAndPreservesZero(): void
    {
        $service = $this->businessService();
        $service->create(['name' => "O'Reilly", 'status' => 0]);
        $service->create(['name' => 'item0', 'status' => 1]);
        $this->assertSame(1, $service->getPage(['filter' => ['keyword' => "O'Reilly"]])->total);
        $this->assertSame(1, $service->getPage(['filter' => ['keyword' => 0]])->total);
        $this->assertSame(0, $service->getPage(['filter' => ['keyword' => "x%' OR 1=1 --"]])->total);
    }

    public function testGeneratedFlowTransformsFormOnceAndRunsEachHookOnce(): void
    {
        $service = $this->businessService();
        $created = $service->store(['form_name' => 'alpha', 'status' => 0]);
        $this->assertSame('ALPHA!', $created['name']);
        $this->assertArrayNotHasKey('owner_id', $created);
        $this->assertSame(['validate', 'saving', 'saved'], $service->events);
        $this->assertSame(1, $this->auditCount());
        $service->events = [];
        $updated = $service->update($created['id'], ['form_name' => 'beta']);
        $this->assertSame('BETA!', $updated['name']);
        $this->assertSame(['validate', 'saving', 'saved'], $service->events);
        $this->assertSame(2, $this->auditCount());
        $service->events = [];
        $service->destroy($created['id']);
        $this->assertSame(['deleting', 'deleted'], $service->events);
        $this->assertSame(0, $this->recordCount());
        $this->assertSame(0, $this->auditCount());
    }

    public function testNativeFiltersSupportLikeRangeZeroAndCustomSorting(): void
    {
        $service = $this->businessService();
        $a = $service->store(['name' => 'alpha', 'status' => 0]);
        $b = $service->store(['name' => 'alpine', 'status' => 1]);
        $service->store(['name' => 'beta', 'status' => 1]);
        $page = $service->paginate(['filter' => ['keyword' => 'alp'], 'sort' => 'id', 'page_size' => 1]);
        $this->assertSame(2, $page->total);
        $this->assertEquals($a['id'], $page->items[0]['id']);
        $this->assertSame(1, $service->paginate(['filter' => ['keyword' => 'alp', 'min_status' => 1]])->total);
        $this->assertEquals($b['id'], $service->paginate(['filter' => ['keyword' => 'alp', 'min_status' => 1]])->items[0]['id']);
        $this->assertSame(1, $service->paginate(['filter' => ['status' => 0]])->total);
        $this->expectException(ServiceException::class);
        $service->paginate(['sort' => 'owner_id']);
    }

    public function testOptionalFieldOverridesStillWork(): void
    {
        $service = $this->businessService();
        $record = $service->store(['name' => 'original', 'status' => 0]);
        $service->restrictFields(['name'], ['id']);
        $this->assertSame(['id' => $record['id']], $service->update($record['id'], ['name' => 'limited']));
        try {
            $service->update($record['id'], ['status' => 1]);
            $this->fail('Expected optional writable restriction');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->getHttpStatus());
        }
        $service->restrictFields([], []);
        $this->assertSame([], $service->show($record['id']));
    }

    public function testCustomOrFiltersCannotEscapeTheAccessScope(): void
    {
        $service = $this->businessService();
        $service->store(['name' => 'own', 'status' => 0]);
        $this->insertOutsideRecord();
        $page = $service->paginate(['filter' => ['either_name' => 'missing']]);
        $this->assertSame(1, $page->total);
        $this->assertSame('own!', $page->items[0]['name']);
    }

    public function testDefaultsNeedNoDuplicateFieldLists(): void
    {
        $service = $this->businessService();
        $service->store(['name' => 'one', 'status' => 1]);
        $service->store(['name' => 'zero', 'status' => 0]);
        $this->assertSame('zero!', $service->paginate(['sort' => 'status'])->items[0]['name']);
        foreach ([['filter' => ['unknown' => 1]], ['sort' => 'form_name']] as $input) {
            try {
                $service->paginate($input);
                $this->fail('Expected invalid query rejection');
            } catch (ServiceException $e) {
                $this->assertSame(422, $e->getHttpStatus());
            }
        }
    }

    public function testHooksAndRelatedWritesRollbackForCreateUpdateAndDelete(): void
    {
        $service = $this->businessService();
        foreach (['saving', 'saved'] as $point) {
            $service->failAt = $point;
            $this->assertHookFailure(fn () => $service->store(['name' => 'rolled back', 'status' => 0]), $point);
            $this->assertSame(0, $this->recordCount());
            $this->assertSame(0, $this->auditCount());
        }
        $service->failAt = null;
        $record = $service->store(['name' => 'original', 'status' => 0]);
        $service->failAt = 'saved';
        $this->assertHookFailure(fn () => $service->update($record['id'], ['name' => 'changed']), 'saved');
        $this->assertSame('original!', $service->show($record['id'])['name']);
        $this->assertSame(1, $this->auditCount());
        foreach (['deleting', 'deleted'] as $point) {
            $service->failAt = $point;
            $this->assertHookFailure(fn () => $service->destroy($record['id']), $point);
            $this->assertSame(1, $this->recordCount());
            $this->assertSame(1, $this->auditCount());
        }
    }

    public function testScopeIsRecheckedAfterHooksAndFieldsAreRestricted(): void
    {
        $service = $this->businessService();
        $record = $service->store(['name' => 'original', 'status' => 0]);
        $service->moveOutsideScope = true;
        try {
            $service->update($record['id'], ['name' => 'changed']);
            $this->fail('Expected scope rejection');
        } catch (ServiceException $e) {
            $this->assertSame(404, $e->getHttpStatus());
        }
        $this->assertSame('original!', $service->show($record['id'])['name']);
        $this->assertSame(1, $this->auditCount());
        try {
            $service->store(['name' => 'forged', 'status' => 0, 'owner_id' => 9]);
            $this->fail('Expected input field rejection');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->getHttpStatus());
        }
        $service->moveOutsideScope = false;
        $service->injectForbiddenField = true;
        try {
            $service->update($record['id'], ['name' => 'forged']);
            $this->fail('Expected persistence field rejection');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->getHttpStatus());
        }
        $this->assertSame('original!', $service->show($record['id'])['name']);
    }

    private function assertHookFailure(callable $operation, string $point): void
    {
        try {
            $operation();
            $this->fail('Expected hook failure');
        } catch (\RuntimeException $e) {
            $this->assertSame($point, $e->getMessage());
        }
    }
}
