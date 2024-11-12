<?php

namespace Ninex\Lib\Http\Services;

use Closure;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ninex\Lib\Exceptions\ServiceException;
use Ninex\Lib\Traits\Database\GeneralHelpers;
use Ninex\Lib\Traits\Database\WithDbTransaction;

abstract class LibService
{
    use DispatchesJobs;
    use GeneralHelpers;
    use WithDbTransaction;

    /**
     * 缓存时间（分钟）
     */
    protected int $cacheMinutes = 60;

    /**
     * 缓存标签
     */
    protected ?string $cacheTag = null;

    /**
     * 是否记录操作日志
     */
    protected bool $enableLog = true;

    /**
     * 魔术方法：获取当前用户
     */
    public function __get($name)
    {
        if ($name === 'user') {
            return $this->user(true);
        }
        return null;
    }

    /**
     * 获取当前用户
     */
    public function user($force = false)
    {
        $user = Auth::guard('api')->user();

        if ($force && !$user) {
            throw $this->createException('未登录', 401);
        }

        return $user;
    }

    /**
     * 缓存包装
     */
    protected function remember(string $key, Closure $callback, ?int $minutes = null)
    {
        if ($this->cacheTag) {
            return Cache::tags($this->cacheTag)->remember($key, $minutes ?? $this->cacheMinutes, $callback);
        }

        return Cache::remember($key, $minutes ?? $this->cacheMinutes, $callback);
    }

    /**
     * 清除缓存
     */
    protected function forget(string|array $keys): void
    {
        if ($this->cacheTag) {
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    Cache::tags($this->cacheTag)->forget($key);
                }
            } else {
                Cache::tags($this->cacheTag)->forget($keys);
            }
            return;
        }

        if (is_array($keys)) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        } else {
            Cache::forget($keys);
        }
    }

    /**
     * 清除标签下所有缓存
     */
    protected function flushCache(): void
    {
        if ($this->cacheTag) {
            Cache::tags($this->cacheTag)->flush();
        }
    }

    /**
     * 记录日志
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        if (!$this->enableLog) {
            return;
        }

        $user = $this->user(false);
        $context = array_merge([
            'user_id' => $user?->id,
            'service' => static::class,
        ], $context);

        Log::log($level, "[Service] {$message}", $context);
    }

    /**
     * 创建业务异常
     */
    protected function createException(string $message = 'Service Exception', int $code = 400): ServiceException
    {
        return new ServiceException($message, $code);
    }

    /**
     * 批量处理
     */
    protected function batch(iterable $items, Closure $callback, int $chunkSize = 100): void
    {
        $chunk = [];
        foreach ($items as $item) {
            $chunk[] = $item;
            if (count($chunk) >= $chunkSize) {
                $callback($chunk);
                $chunk = [];
            }
        }
        if (!empty($chunk)) {
            $callback($chunk);
        }
    }
}
