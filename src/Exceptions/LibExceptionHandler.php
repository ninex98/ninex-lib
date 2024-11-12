<?php

namespace Ninex\Lib\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LibExceptionHandler extends ExceptionHandler
{
    /**
     * 不需要记录的异常类型
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        AuthenticationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
        ServiceException::class,
    ];

    /**
     * 转换异常为 HTTP 响应
     *
     * @param \Illuminate\Http\Request $request
     * @param Throwable $e
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws Throwable
     */
    public function render($request, Throwable $e)
    {
        // API 请求的统一错误处理
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * 处理 API 异常
     *
     * @param Throwable $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleApiException(Throwable $e)
    {
        $error = $this->convertExceptionToArray($e);

        return response()->json([
            'code' => $error['code'],
            'message' => $error['message'],
            'data' => $error['data'] ?? null,
        ], 200); // 统一使用 200 状态码，通过 code 字段区分错误
    }

    /**
     * 转换异常为数组
     *
     * @param Throwable $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e): array
    {
        // 开发环境返回详细信息
        if (config('app.debug')) {
            return [
                'code' => $this->getExceptionCode($e),
                'message' => $e->getMessage(),
                'data' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->take(10)->toArray(),
                ],
            ];
        }

        return [
            'code' => $this->getExceptionCode($e),
            'message' => $this->getExceptionMessage($e),
        ];
    }

    /**
     * 获取异常代码
     *
     * @param Throwable $e
     * @return int
     */
    protected function getExceptionCode(Throwable $e): int
    {
        return match (true) {
            // 业务异常
            $e instanceof ServiceException => $e->getCode() ?: 400,

            // 认证异常
            $e instanceof AuthenticationException => 401,

            // 验证异常
            $e instanceof ValidationException => 422,

            // 模型未找到
            $e instanceof ModelNotFoundException => 404,

            // HTTP 异常
            $e instanceof HttpException => $e->getStatusCode(),

            // 其他异常
            default => 500,
        };
    }

    /**
     * 获取异常消息
     *
     * @param Throwable $e
     * @return string
     */
    protected function getExceptionMessage(Throwable $e): string
    {
        return match (true) {
            // 业务异常&&验证异常
            $e instanceof ServiceException, $e instanceof ValidationException => $e->getMessage(),

            // 认证异常
            $e instanceof AuthenticationException => '未登录或登录已过期',

            // 模型未找到
            $e instanceof ModelNotFoundException => '请求的资源不存在',

            // HTTP 异常
            $e instanceof HttpException => $e->getMessage() ?: '请求错误',

            // 其他异常
            default => config('app.debug') ? $e->getMessage() : '服务器内部错误',
        };
    }
}
