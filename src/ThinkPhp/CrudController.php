<?php

namespace Ninex\Lib\ThinkPhp;

use Closure;
use Ninex\Lib\Core\CrudService;
use think\{Request, Response};

abstract class CrudController
{
    use RespondsWithService;

    protected CrudService $service;
    private ?Closure $serviceFactory = null;

    /** 注入核心服务或工厂。 / Inject a core service or factory. */
    public function __construct(protected Request $request, CrudService|Closure $service)
    {
        if ($service instanceof Closure) {
            $this->serviceFactory = $service;
        } else {
            $this->service = $service;
        }
    }

    /** 列表。 / List records. */
    public function index(): Response
    {
        return $this->respond(fn () => $this->service->paginate($this->request->get())->toArray());
    }

    /** 详情。 / Show a record. */
    public function read($id): Response
    {
        return $this->respond(fn () => $this->service->show($id));
    }

    /** 创建。 / Create a record. */
    public function save(): Response
    {
        return $this->respond(fn () => $this->service->store($this->request->post()), 201);
    }

    /** 更新。 / Update a record. */
    public function update($id): Response
    {
        return $this->respond(fn () => $this->service->update($id, $this->request->put()));
    }

    /** 删除。 / Delete a record. */
    public function delete($id): Response
    {
        return $this->respond(function () use ($id) {
            $this->service->destroy($id);
            return [];
        });
    }

}
