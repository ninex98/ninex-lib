<?php

namespace Ninex\Lib\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ninex\Lib\Http\Resources\LibResource;
use Ninex\Lib\Http\Traits\ResponseTrait;
use Ninex\Lib\Traits\Database\WithDbTransaction;
use Throwable;

abstract class LibController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
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

    /**
     * 是否使用事务
     */
    protected bool $useTransaction = true;

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
        $this->setupCors();
    }

    /**
     * 设置跨域
     */
    protected function setupCors(): void
    {
        if (config('app.env') === 'production') {
            return;
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Authorization');
    }

    /**
     * 列表
     */
    public function index()
    {
        $result = $this->service->paginate($this->request->all());
        return $this->success($this->resource::collection($result));
    }

    /**
     * 详情
     */
    public function show($id)
    {
        $result = $this->service->show($id);
        return $this->success($this->resource::make($result));
    }

    /**
     * 创建
     * @throws Throwable
     */
    public function store()
    {
        $result = $this->runWithTransaction(function () {
            return $this->service->store($this->request->all());
        });

        return $this->success($this->resource::make($result));
    }

    /**
     * 更新
     * @throws Throwable
     */
    public function update($id)
    {
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
        $this->runWithTransaction(function () use ($id) {
            $this->service->destroy($id);
        });

        return $this->noContent();
    }

    /**
     * 执行事务
     */
    protected function runWithTransaction(callable $callback)
    {
        if (!$this->useTransaction) {
            return $callback();
        }

        return $this->transaction($callback);
    }
}
