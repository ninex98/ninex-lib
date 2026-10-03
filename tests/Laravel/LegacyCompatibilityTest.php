<?php

namespace Ninex\Lib\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ninex\Lib\Tests\Fixtures\Item;

// These declarations intentionally use the signatures applications inherited from 1.0.9.
class LegacyCustomException extends \Ninex\Lib\Exceptions\ServiceException
{
    protected $data;
    public function getData()
    {
        return $this->data;
    }
}
class LegacyCustomModel extends Item
{
    protected static function boot()
    {
        parent::boot();
    }
}

class LegacyCustomClient extends \Ninex\Lib\Http\Clients\LibClient
{
    protected \GuzzleHttp\Client $client;
}

class LegacyCompatibilityTest extends TestCase
{
    public function testLegacySubclassSignaturesStillLoad(): void
    {
        $this->assertSame(['reason' => 'old'], (new LegacyCustomException('bad', 400, ['reason' => 'old']))->getData());
        $this->assertSame('old model', LegacyCustomModel::create(['name' => 'old model'])->name);
        $presenter = new class (Request::create('/api/test')) extends \Ninex\Lib\Http\Controllers\LibController {
            protected function error(?string $message = null, \Ninex\Lib\Enums\ErrorCode|int $code = \Ninex\Lib\Enums\ErrorCode::SYSTEM, int $statusCode = 200, mixed $data = null): \Illuminate\Http\JsonResponse
            {
                return parent::error($message, $code, $statusCode, $data);
            }
            public function legacyError()
            {
                return $this->error('bad', 400);
            }
        };
        $this->assertSame(200, $presenter->legacyError()->getStatusCode());
        $this->assertInstanceOf(LegacyCustomClient::class, new LegacyCustomClient());
        \Illuminate\Support\Facades\Auth::shouldReceive('guard')->with('api')->once()->andReturn(new class () {
            public function user()
            {
                return 'legacy-api-user';
            }
        });
        $this->assertSame('legacy-api-user', (new class () extends \Ninex\Lib\Http\Services\LibService {})->user());
    }

    public function testLegacyExceptionCustomizationHooksStillRun(): void
    {
        $handler = new class ($this->app) extends \Ninex\Lib\Exceptions\LibExceptionHandler {
            protected function getExceptionCode(\Throwable $e): int
            {
                return parent::getExceptionCode($e) + 1000;
            }
            protected function getExceptionMessage(\Throwable $e): string
            {
                return 'custom: '.parent::getExceptionMessage($e);
            }
        };
        $response = $handler->render(Request::create('/api/test'), new LegacyCustomException('old message', 422, ['field' => 'name']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1422, $response->getData(true)['code']);
        $this->assertSame('custom: old message', $response->getData(true)['message']);
        $this->assertSame(['field' => 'name'], $response->getData(true)['data']);
    }

    public function testServicesWithoutModelsCanStillUseTransactions(): void
    {
        $service = new class () extends \Ninex\Lib\Http\Services\LibService {
            public function run()
            {
                return $this->transaction(function () {
                    DB::table('items')->insert(['name' => 'rollback']);
                    throw new \RuntimeException('rollback');
                });
            }
        };
        try {
            $service->run();
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback', $e->getMessage());
        }
        $this->assertSame(0, Item::count());
    }

    public function testLegacyPaginationAndEmptyBatchResultsArePreserved(): void
    {
        Item::create(['name' => 'one']);
        Item::create(['name' => 'two']);
        $page = Item::simplePaginate([], [], 1);
        $this->assertSame(2, $page->total());
        $this->assertSame(1, $page->count());
        $this->assertTrue(Item::insertIgnore([]));
        $this->assertTrue(Item::batchUpdate([], 'id'));
    }

    public function testCacheFailureCannotTurnCommittedWritesIntoFailedOperations(): void
    {
        config(['ninexlib.model_cache.enabled' => true]);
        $reported = 0;
        app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\RuntimeException $e) use (&$reported) {
            $reported++;
            return false;
        });
        \Illuminate\Support\Facades\Cache::shouldReceive('forget')->once()->andThrow(new \RuntimeException('cache offline'));
        \Illuminate\Support\Facades\Cache::shouldReceive('get')->once()->andThrow(new \RuntimeException('cache offline'));
        $item = Item::create(['name' => 'committed']);
        $this->assertTrue($item->exists);
        $this->assertSame('committed', Item::findWithCache($item->id)->name);
        $this->assertSame(2, $reported);
    }
}
