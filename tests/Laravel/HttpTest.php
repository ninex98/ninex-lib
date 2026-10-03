<?php

namespace Ninex\Lib\Tests;

use Illuminate\Support\Facades\Route;
use Ninex\Lib\Http\Traits\ResponseTrait;
use Ninex\Lib\Core\ServiceException;

class HttpTest extends TestCase
{
    public function testAutomaticExceptionRegistrationAndHttpStatus(): void
    {
        Route::get('api/denied', fn () => throw new \Illuminate\Auth\Access\AuthorizationException('private'));
        Route::get('api/validation', fn () => validator([], ['name' => 'required'])->validate());
        Route::get('api/business', fn () => throw new ServiceException('conflict', 10001, ['x' => 1], null, 409));
        Route::get('api/limit', fn () => throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(30));
        Route::get('api/failure', fn () => throw new \RuntimeException('secret'));
        $this->getJson('/api/denied')->assertForbidden()->assertJsonPath('code', 403);
        $this->getJson('/api/validation')->assertUnprocessable()->assertJsonStructure(['data' => ['errors' => ['name']]]);
        $this->getJson('/api/business')->assertStatus(409)->assertJsonPath('code', 10001)->assertJsonPath('data.x', 1);
        $this->getJson('/api/limit')->assertStatus(429)->assertHeader('Retry-After', '30');
        $this->getJson('/api/failure')->assertStatus(500)->assertJsonPath('message', '服务器内部错误');
        config(['ninexlib.exceptions.legacy_http_200' => true]);
        $this->getJson('/api/denied')->assertOk()->assertJsonPath('code', 403);
    }
    public function testResponseValuesAndResourceConditionalFields(): void
    {
        $presenter = new class () {
            use ResponseTrait;
            public function emit($v)
            {
                return $this->success($v);
            }
        };
        foreach ([[], false, '', 0, null] as $value) {
            $this->assertSame($value, $presenter->emit($value)->getData(true)['data']);
        }
        $resource = new class (['id' => 1]) extends \Illuminate\Http\Resources\Json\JsonResource {
            public function toArray($request): array
            {
                return ['id' => $this->resource['id'], 'secret' => $this->when(false, 'hidden')];
            }
        };
        $this->assertSame(['id' => 1], $presenter->emit($resource)->getData(true)['data']);
        $page = new \Illuminate\Pagination\LengthAwarePaginator([['id' => 1]], 1, 15);
        $this->assertSame([['id' => 1]], $presenter->emit($resource::collection($page))->getData(true)['data']['data']);
    }
    public function testHttpClientUsesTlsConfigAndHandlesEmptyAndInvalidResponses(): void
    {
        config(['ninexlib.http.timeout' => 7]);
        $make = function ($mock = null) {
            return new class ([], $mock) extends \Ninex\Lib\Http\Clients\LibClient {
                public function run()
                {
                    return $this->get('/test');
                }
                public function option($key)
                {
                    return $this->client->getConfig($key);
                }
            };
        };
        $this->assertTrue($make()->option('verify'));
        $this->assertSame(7, $make()->option('timeout'));
        $mock = new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(204), new \GuzzleHttp\Psr7\Response(200, [], 'invalid')]);
        $client = $make(new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mock)]));
        $this->assertSame([], $client->run());
        try {
            $client->run();
            $this->fail('Expected invalid JSON error');
        } catch (ServiceException $e) {
            $this->assertSame(502, $e->getHttpStatus());
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }
    public function testSqlCollectorIsBoundedRedactsBindingsAndResets(): void
    {
        config(['app.debug' => true, 'ninexlib.sql.enabled' => true, 'ninexlib.sql.limit' => 1]);
        \Ninex\Lib\Support\SqlRecord::listen();
        $middleware = new \Ninex\Lib\Http\Middleware\ResetSqlRecord();
        $middleware->handle(request(), function () {
            \Illuminate\Support\Facades\DB::select('select ? as value', ['private']);
            \Illuminate\Support\Facades\DB::select('select 2');
            $records = app(\Ninex\Lib\Support\SqlRecord::class)->all();
            $this->assertCount(1, $records);
            $this->assertStringNotContainsString('private', json_encode($records));
            return response()->json([]);
        });
        $this->assertSame([], app(\Ninex\Lib\Support\SqlRecord::class)->all());
    }
}
