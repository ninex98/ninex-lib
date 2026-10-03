<?php

namespace Ninex\Lib\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ninex\Lib\Http\Controllers\LibController;

class LifecycleIdentityMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $request->setUserResolver(fn () => new GenericUser(['id' => (int) $request->header('X-Test-Actor')]));
        return $next($request);
    }
}

class LifecycleService
{
    public int $actorId;

    public function __construct(Request $request)
    {
        $actor = $request->user();
        if (!$actor) {
            throw new \LogicException('Identity must be available before service resolution.');
        }
        $this->actorId = $actor->getAuthIdentifier();
    }

    public function show($id): array
    {
        return ['id' => $id, 'actor_id' => $this->actorId];
    }
}

class LifecycleController extends LibController
{
    protected ?string $serviceClass = LifecycleService::class;

    public function inspect(Request $request, $id)
    {
        return $this->success([
            'actor_id' => $this->service->actorId,
            'id' => $id,
            'value' => $this->request->query('value'),
            'current_request' => $this->request === $request,
        ]);
    }
}

class ResponseOnlyController extends LibController
{
    public function index()
    {
        return $this->success(['ready' => true]);
    }
}

class ControllerLifecycleTest extends TestCase
{
    public function testServiceIsResolvedOnceAfterMiddlewareAndRefreshedForEachDispatch(): void
    {
        $resolutions = 0;
        $this->app->bind(LifecycleService::class, function ($app) use (&$resolutions) {
            $resolutions++;
            return new LifecycleService($app->make(Request::class));
        });
        $controller = $this->app->make(LifecycleController::class);
        $this->app->instance(LifecycleController::class, $controller);
        $this->assertSame(0, $resolutions);
        Route::get('api/lifecycle/{id}', [LifecycleController::class, 'inspect'])->middleware(LifecycleIdentityMiddleware::class);

        $this->getJson('/api/lifecycle/first?value=one', ['X-Test-Actor' => '1'])
            ->assertOk()->assertJsonPath('data.actor_id', 1)->assertJsonPath('data.id', 'first')
            ->assertJsonPath('data.value', 'one')->assertJsonPath('data.current_request', true);
        $this->assertSame(1, $resolutions);
        $this->getJson('/api/lifecycle/second?value=two', ['X-Test-Actor' => '2'])
            ->assertOk()->assertJsonPath('data.actor_id', 2)->assertJsonPath('data.id', 'second')
            ->assertJsonPath('data.value', 'two')->assertJsonPath('data.current_request', true);
        $this->assertSame(2, $resolutions);
    }

    public function testInheritedActionsSupportDirectCallsAndResolveOnceDuringDispatch(): void
    {
        $resolutions = 0;
        $this->app->bind(LifecycleService::class, function ($app) use (&$resolutions) {
            $resolutions++;
            return new LifecycleService($app->make(Request::class));
        });
        $controller = new class (Request::create('/old-request')) extends LibController {
            protected ?string $serviceClass = LifecycleService::class;
        };
        foreach ([1, 2] as $actorId) {
            $request = Request::create('/direct');
            $this->app->instance('request', $request);
            $request->setUserResolver(fn () => new GenericUser(['id' => $actorId]));
            $this->assertSame($actorId, $controller->show(1)->getData(true)['data']['actor_id']);
        }
        $this->assertSame(2, $resolutions);
        $this->assertSame(2, $controller->callAction('show', [1])->getData(true)['data']['actor_id']);
        $this->assertSame(3, $resolutions);
        $controller->show(1);
        $this->assertSame(4, $resolutions);
    }

    public function testResponseOnlyControllerDoesNotRequireACrudService(): void
    {
        Route::get('api/ready', [ResponseOnlyController::class, 'index']);
        $this->getJson('/api/ready')->assertOk()->assertJsonPath('data.ready', true);
    }
}
