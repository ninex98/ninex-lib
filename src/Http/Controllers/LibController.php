<?php

namespace Ninex\Lib\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ninex\Lib\Core\CrudService;
use Ninex\Lib\Core\Page;
use Ninex\Lib\Http\Resources\LibResource;
use Ninex\Lib\Http\Traits\ResponseTrait;
use Ninex\Lib\Traits\Database\WithDbTransaction;
use Throwable;

abstract class LibController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
    use WithDbTransaction;
    use ResponseTrait;

    /**
     * 请求实例
     */
    protected Request $request;

    /**
     * 服务实例
     */
    protected $service;

    /** Resolve per action, after authentication middleware. */
    protected ?string $serviceClass = null;

    /**
     * 是否使用事务
     */
    protected bool $useTransaction = false;

    /** Opt in to Laravel Gate/Policy for legacy model services; core services authorize themselves. */
    protected bool $usePolicy = false;

    /**
     * 资源类
     */
    protected string $resource = LibResource::class;

    /**
     * 构造函数
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 列表
     */
    public function index()
    {
        $this->prepareCrud();
        $this->authorizeCrud('viewAny');
        $result = $this->service->paginate($this->request->all());
        if ($result instanceof Page) {
            return $this->success(new Page($this->resource::collection($result->items)->resolve($this->request), $result->total, $result->pageSize, $result->currentPage));
        }
        return $this->success($this->resource::collection($result));
    }

    /**
     * 详情
     */
    public function show($id)
    {
        $this->prepareCrud();
        $result = $this->service->show($id);
        $this->authorizeCrud('view', $result);
        return $this->success($this->resource::make($result));
    }

    /**
     * 创建
     * @throws Throwable
     */
    public function store()
    {
        $this->prepareCrud();
        $this->authorizeCrud('create');
        $result = $this->runWithTransaction(function () {
            return $this->service->store($this->request->all());
        });

        return $this->success($this->resource::make($result))->setStatusCode(config('ninexlib.exceptions.legacy_http_200', false) ? 200 : 201);
    }

    /**
     * 更新
     * @throws Throwable
     */
    public function update($id)
    {
        $this->prepareCrud();
        if ($this->usePolicy && !$this->service instanceof CrudService) {
            $this->authorizeCrud('update', $this->service->show($id));
        }
        $result = $this->runWithTransaction(function () use ($id) {
            return $this->service->update($id, $this->request->all());
        });

        return $this->success($this->resource::make($result));
    }


    /**
     * 删除
     * @throws Throwable
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $this->prepareCrud();
        if ($this->usePolicy && !$this->service instanceof CrudService) {
            $this->authorizeCrud('delete', $this->service->show($id));
        }
        $this->runWithTransaction(function () use ($id) {
            $this->service->destroy($id);
        });

        return $this->noContent();
    }

    /**
     * 在当前请求的认证中间件之后解析服务
     */
    protected function prepareCrud(): void
    {
        $this->request = app(Request::class);
        if ($this->serviceClass) {
            $this->service = app($this->serviceClass);
        }
        if (!$this->service) {
            throw new \LogicException('Configure serviceClass or inject a CRUD service.');
        }
    }

    protected function authorizeCrud(string $ability, $record = null): void
    {
        if (!$this->service) {
            throw new \LogicException('Bind a CRUD service in the controller constructor.');
        }
        if (!$this->usePolicy || $this->service instanceof CrudService) {
            return;
        }
        $this->authorize($ability, $record ?? get_class($this->service->model()));
    }

    protected function runWithTransaction(callable $callback)
    {
        if (!$this->useTransaction) {
            return $callback();
        }

        return $this->transaction($callback);
    }
}
