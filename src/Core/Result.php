<?php

namespace Ninex\Lib\Core;

final class Result
{
    public static function success(mixed $data = [], string $message = '操作成功', int $code = 0): array
    {
        return ['code' => $code, 'message' => $message, 'data' => $data];
    }

    public static function error(ServiceException $exception): array
    {
        return ['code' => $exception->getCode(), 'message' => $exception->getMessage(), 'data' => $exception->getData()];
    }
}
