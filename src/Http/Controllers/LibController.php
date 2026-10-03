<?php

namespace Ninex\Lib\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ninex\Lib\Contracts\CrudActions;
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
     * 请求实例。 / Current request.
     */
    protected Request $request;

    /**
     * 服务实例。 / Business service.
     */
    protected $service;

    /** 认证后按请求解析服务。 / Resolve per action after authentication middleware. */
    protected ?string $serviceClass = null;

    private bool $crudPreparedForDispatch = false;

    /**
     * 是否使用事务。 / Optional controller transaction for inherited legacy actions.
     */
    protected bool $useTransaction = false;

    /** 旧模型服务可选 Policy；CrudActions 自行授权。 / Optional legacy Policy checks; CrudActions authorize themselves. */
    protected bool $usePolicy = false;

    /**
     * 资源类。 / Response resource class.
     */
    protected string $resource = LibResource::class;

    /**
     * 构造函数。 / Inject the request.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 路由中间件执行后，统一准备业务服务再分发 action。 / Resolve the service after route middleware and before dispatch.
     */
    public function callAction($method, $parameters)
    {
        $previous = $this->crudPreparedForDispatch;
        try {
            if ($this->serviceClass || $this->service) {
                $this->prepareCrud();
                $this->crudPreparedForDispatch = true;
            }
            return parent::callAction($method, $parameters);
        } finally {
            $this->crudPreparedForDispatch = $previous;
        }
    }

    /**
     * 列表。 / List records.
     */
    public function index()
    {
        $this->prepareInheritedCrud();
        $this->authorizeCrud('viewAny');
        $result = $this->service->paginate($this->request->all());
        return $this->respondWithCollection($result);
    }

    /**
     * 详情。 / Show a record.
     */
    public function show($id)
    {
        $this->prepareInheritedCrud();
        $result = $this->service->show($id);
        $this->authorizeCrud('view', $result);
        return $this->success($this->resource::make($result));
    }

    /**
     * 创建。 / Create a record.
     * @throws Throwable
     */
    public function store()
    {
        $this->prepareInheritedCrud();
        $this->authorizeCrud('create');
        $result = $this->runWithTransaction(function () {
            return $this->service->store($this->request->all());
        });

        return $this->respondWithCreatedResource($result);
    }

    /**
     * 更新。 / Update a record.
     * @throws Throwable
     */
    public function update($id)
    {
        $this->prepareInheritedCrud();
        if ($this->usePolicy && !$this->service instanceof CrudActions) {
            $this->authorizeCrud('update', $this->service->show($id));
        }
        $result = $this->runWithTransaction(function () use ($id) {
            return $this->service->update($id, $this->request->all());
        });

        return $this->success($this->resource::make($result));
    }


    /**
     * 删除。 / Delete a record.
     * @throws Throwable
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $this->prepareInheritedCrud();
        if ($this->usePolicy && !$this->service instanceof CrudActions) {
            $this->authorizeCrud('delete', $this->service->show($id));
        }
        $this->runWithTransaction(function () use ($id) {
            $this->service->destroy($id);
        });

        return $this->noContent();
    }

    /**
     * 转换列表资源并保留分页信息。 / Transform collection resources while preserving pagination.
     */
    protected function respondWithCollection($result): \Illuminate\Http\JsonResponse
    {
        if ($result instanceof Page) {
            return $this->success(new Page($this->resource::collection($result->items)->resolve($this->request), $result->total, $result->pageSize, $result->currentPage));
        }
        return $this->success($this->resource::collection($result));
    }

    /**
     * 返回创建的资源，兼容旧 HTTP 状态配置。 / Return a created resource with legacy status compatibility.
     */
    protected function respondWithCreatedResource($result): \Illuminate\Http\JsonResponse
    {
        return $this->success($this->resource::make($result))->setStatusCode(config('ninexlib.exceptions.legacy_http_200', false) ? 200 : 201);
    }

    /** 兼容直接调用旧 CRUD，路由分发时不重复解析。 / Preserve direct legacy actions without double resolution during dispatch. */
    private function prepareInheritedCrud(): void
    {
        if (!$this->crudPreparedForDispatch) {
            $this->prepareCrud();
        }
    }

    /**
     * 在当前请求的认证中间件之后解析服务。 / Resolve the service using the current authenticated request.
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

    /** 仅为旧服务补充 Policy 检查。 / Add opt-in Policy checks for legacy services only. */
    protected function authorizeCrud(string $ability, $record = null): void
    {
        if (!$this->service) {
            throw new \LogicException('Bind a CRUD service in the controller constructor.');
        }
        if (!$this->usePolicy || $this->service instanceof CrudActions) {
            return;
        }
        $this->authorize($ability, $record ?? get_class($this->service->model()));
    }

    /** 旧控制器可选事务；生成业务由 Service 管理事务。 / Optional legacy wrapping; generated services own transactions. */
    protected function runWithTransaction(callable $callback)
    {
        if (!$this->useTransaction) {
            return $callback();
        }

        return $this->transaction($callback);
    }
}
