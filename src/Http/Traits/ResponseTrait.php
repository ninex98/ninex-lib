<?php

namespace Ninex\Lib\Http\Traits;

use Ninex\Lib\Enums\ErrorCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Ninex\Lib\Support\SqlRecord;

trait ResponseTrait
{
    /**
     * 默认响应格式
     */
    protected array $responseFormat = [
        'code' => 0,
        'message' => '',
        'data' => null
    ];

    /**
     * 分页字段映射
     */
    protected array $paginationMap = [
        'total' => 'total',
        'per_page' => 'page_size',
        'current_page' => 'current_page',
        'last_page' => 'total_pages'
    ];

    /**
     * 成功响应
     */
    protected function success($data = [], string $message = '操作成功', int $code = 0): JsonResponse
    {
        // 处理不同类型的数据
        $responseData = match(true) {
            $data instanceof Model => $this->handleModel($data),
            $data instanceof AbstractPaginator => $this->handlePaginator($data),
            $data instanceof ResourceCollection => $this->handleResourceCollection($data),
            $data instanceof JsonResource => $this->handleJsonResource($data),
            default => $data
        };

        return $this->response($this->formatData($responseData, $message, $code));
    }

    /**
     * 分页响应
     */
    protected function pagination(LengthAwarePaginator $paginator, string $message = '成功'): JsonResponse
    {
        return $this->success(
            $this->formatPaginatedData($paginator->toArray()),
            $message
        );
    }

    /**
     * 无内容响应
     */
    protected function noContent(string $message = '操作成功'): JsonResponse
    {
        return $this->success([], $message);
    }

    /**
     * 错误响应
     *
     * @param string|null $message 错误信息
     * @param ErrorCode|int $code 错误码
     * @param int $statusCode HTTP状态码
     * @param mixed|null $data 额外数据
     */
    protected function error(
        ?string $message = null,
        ErrorCode|int $code = ErrorCode::SYSTEM,
        int $statusCode = 200,
        mixed $data = null
    ): JsonResponse {
        // 获取错误码的值
        $errorCode = $code instanceof ErrorCode ? $code->value : $code;

        return $this->response([
            'code' => $errorCode,
            'message' => $message ?? ($code instanceof ErrorCode ? $code->message() : '系统错误'),
            'data' => $data,
        ], $statusCode);
    }

    /**
     * 处理 Model 数据
     */
    protected function handleModel(Model $model): array
    {
        return $model->toArray();
    }

    /**
     * 处理分页数据
     */
    protected function handlePaginator(AbstractPaginator $paginator): array
    {
        $data = $paginator->toArray();

        if (isset($paginator->additional)) {
            $data = array_merge($data, ['additional' => $paginator->additional]);
        }

        return $this->formatPaginatedData($data);
    }

    /**
     * 处理资源集合
     */
    protected function handleResourceCollection(ResourceCollection $collection): array
    {
        $resource = $collection->resource;
        $additional = $collection->additional;

        if ($resource instanceof AbstractPaginator) {
            return $this->formatPaginatedData(array_merge(
                $resource->toArray(),
                ['additional' => $additional]
            ));
        }

        return $collection->toArray(request());
    }

    /**
     * 处理 JsonResource
     */
    protected function handleJsonResource(JsonResource $resource): array
    {
        return $resource->toArray(request());
    }

    /**
     * 格式化分页数据
     */
    protected function formatPaginatedData(array $paginated): array
    {
        $paginationInfo = [];
        foreach ($this->paginationMap as $from => $to) {
            $paginationInfo[$to] = intval($paginated[$from] ?? 0);
        }

        return array_merge(
            ['data' => $paginated['data']],
            $paginationInfo,
            Arr::get($paginated, 'additional', [])
        );
    }

    /**
     * 格式化响应数据
     */
    protected function formatData($data, string $message, int $code): array
    {
        return array_merge($this->responseFormat, [
            'code' => $code,
            'message' => $message,
            'data' => ($data||is_numeric($data)) ? $data : $this->responseFormat['data'],
        ], $this->debug());
    }

    /**
     * 调试信息
     * @return array
     */
    protected function debug()
    {
        if (config('app.debug')) {
            return [
                'debug' => [
                    'sql' => SqlRecord::$sql
                ]
            ];
        }
        return [];
    }

    /**
     * 创建 JsonResponse
     */
    protected function response(
        $data = [],
        int $status = 200,
        array $headers = [],
        int $options = 0
    ): JsonResponse {
        return new JsonResponse($data, $status, $headers, $options);
    }

    /**
     * 设置响应格式
     */
    protected function setResponseFormat(array $format): self
    {
        $this->responseFormat = $format;
        return $this;
    }

    /**
     * 设置分页字段映射
     */
    protected function setPaginationMap(array $map): self
    {
        $this->paginationMap = $map;
        return $this;
    }
}
