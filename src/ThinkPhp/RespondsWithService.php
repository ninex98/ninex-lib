<?php

namespace Ninex\Lib\ThinkPhp;

use Closure;
use Ninex\Lib\Core\{Result, ServiceException};
use think\{App, Container, Response};
use think\exception\Handle;
use Throwable;

/** 旧核心控制器与新业务控制器共用响应边界。 / Shared response boundary for both controller styles. */
trait RespondsWithService
{
    /** 在 action 内处理服务解析和异常。 / Resolve services and render errors inside the action boundary. */
    protected function respond(Closure $operation, int $status = 200): Response
    {
        $app = Container::getInstance();
        $legacy = $app instanceof App && $app->config->get('ninexlib.legacy_http_200', false);
        try {
            if ($this->serviceFactory) {
                $this->service = ($this->serviceFactory)();
            }
            return Response::create(Result::success($operation()), 'json', $legacy ? 200 : $status);
        } catch (Throwable $e) {
            if ($app instanceof App && !$e instanceof ServiceException) {
                try {
                    $app->make(Handle::class)->report($e);
                } catch (Throwable) { /* A logging outage must not replace the original response. */
                }
            }
            return ExceptionHandler::jsonResponse($e, $legacy);
        }
    }
}
