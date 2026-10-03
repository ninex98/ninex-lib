<?php

namespace Ninex\Lib\ThinkPhp;

use Ninex\Lib\Core\Result;
use Ninex\Lib\Core\ServiceException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use Throwable;

/** Opt in through app/provider.php; preserves HTML handling for non-API requests. */
class ExceptionHandler extends Handle
{
    public function render(Request $request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException || (!$request->isJson() && !str_starts_with($request->pathinfo(), 'api/'))) {
            return parent::render($request, $e);
        }
        return $this->renderApi($request, $e);
    }

    public function renderApi(Request $request, Throwable $e): Response
    {
        return self::jsonResponse($e, (bool) $this->app->config->get('ninexlib.legacy_http_200', false));
    }

    public static function jsonResponse(Throwable $e, bool $legacyHttp200 = false): Response
    {
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }
        $status = match (true) {
            $e instanceof ServiceException => $e->getHttpStatus(),
            $e instanceof ValidateException => 422,
            $e instanceof \think\db\exception\ModelNotFoundException, $e instanceof \think\db\exception\DataNotFoundException => 404,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 500,
        };
        $body = $e instanceof ServiceException ? Result::error($e) : [
            'code' => $status,
            'message' => $status >= 500 ? '服务器内部错误' : ($status === 404 ? '请求的资源不存在' : ($e->getMessage() ?: '请求错误')),
            'data' => $e instanceof ValidateException ? ['errors' => $e->getError()] : null,
        ];
        $headers = $e instanceof HttpException ? $e->getHeaders() : [];
        return Response::create($body, 'json', $legacyHttp200 ? 200 : $status)->header($headers);
    }
}
