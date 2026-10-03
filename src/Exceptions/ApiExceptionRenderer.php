<?php

namespace Ninex\Lib\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Ninex\Lib\Core\ServiceException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if ((!$request->expectsJson() && !$request->is('api/*')) || $e instanceof HttpResponseException) {
            return null;
        }
        $error = $this->describe($e);
        $status = $error['status'];
        $body = array_intersect_key($error, array_flip(['code', 'message', 'data']));
        if (config('app.debug')) {
            $body['debug'] = ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()];
        }
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
        return new JsonResponse($body, config('ninexlib.exceptions.legacy_http_200', false) ? 200 : $status, $headers);
    }
    public function describe(Throwable $e): array
    {
        $status = match (true) {
            $e instanceof ServiceException => $e->getHttpStatus(),
            $e instanceof ValidationException => $e->status,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => $e->status() ?? 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
        $message = match (true) {
            $e instanceof ServiceException, $e instanceof ValidationException => $e->getMessage(),
            $status === 401 => '未登录或登录已过期',
            $status === 403 => '无权限访问',
            $status === 404 => '请求的资源不存在',
            $status >= 500 => '服务器内部错误',
            default => $e->getMessage() ?: '请求错误',
        };
        $data = match (true) {
            $e instanceof ServiceException => $e->getData(),
            $e instanceof ValidationException => ['errors' => $e->errors()],
            default => null,
        };
        return ['code' => $e instanceof ServiceException ? $e->getCode() : $status, 'status' => $status, 'message' => $message, 'data' => $data];
    }

}
