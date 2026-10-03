<?php

namespace Ninex\Lib\ThinkPhp;

use Closure;
use Ninex\Lib\Contracts\CrudActions;
use think\Request;

/** 业务控制器基类；五个 action 显式保留在生成文件中。 / Generated controllers declare their own actions. */
abstract class ServiceController
{
    use RespondsWithService;

    protected CrudActions $service;
    private ?Closure $serviceFactory = null;

    /** 每个响应入口解析服务，避免缓存身份。 / Resolve a fresh service inside each response boundary. */
    public function __construct(protected Request $request, CrudActions|Closure $service)
    {
        if ($service instanceof Closure) {
            $this->serviceFactory = $service;
        } else {
            $this->service = $service;
        }
    }
}
