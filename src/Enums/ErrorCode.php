<?php

namespace Ninex\Lib\Enums;

enum ErrorCode: int
{
    case SUCCESS = 0;
    case SYSTEM = 500;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case VALIDATION = 422;
    case SERVICE = 400;

    /**
     * 获取错误描述
     */
    public function message(): string
    {
        return match($this) {
            self::SUCCESS => '操作成功',
            self::SYSTEM => '系统错误',
            self::UNAUTHORIZED => '未授权访问',
            self::FORBIDDEN => '无权限访问',
            self::NOT_FOUND => '资源不存在',
            self::VALIDATION => '数据验证失败',
            self::SERVICE => '业务处理失败',
        };
    }
}
