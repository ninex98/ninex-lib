<?php

namespace Ninex\Lib\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ninex\Lib\Traits\Database\WithDbTransaction;
use Throwable;

abstract class LibJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use WithDbTransaction;

    /**
     * 任务重试次数
     */
    public int $tries = 3;

    /**
     * 任务超时时间（秒）
     */
    public int $timeout = 60;

    /**
     * 任务重试间隔（秒）
     */
    public int $backoff = 3;

    /**
     * 是否在失败时记录日志
     */
    protected bool $shouldLogFailure = true;

    /**
     * 任务标识符
     */
    protected ?string $jobIdentifier = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->jobIdentifier = $this->generateJobIdentifier();
        $this->onQueue($this->determineQueue());
    }

    /**
     * 执行任务
     */
    public function handle()
    {
        // 设置事务钩子
        $this->beforeTransaction(fn() => $this->beforeExecute())
            ->afterTransaction(fn($result) => $this->afterExecute($result))
            ->onTransactionError(fn($e) => $this->handleFailure($e));

        // 直接使用 transaction
        return $this->transaction(function () {
            return $this->execute();
        });
    }

    /**
     * 实际的任务执行逻辑（子类必须实现）
     */
    abstract protected function execute();

    /**
     * 任务执行前的处理
     */
    protected function beforeExecute(): void
    {
        Log::info("[Job Started] {$this->jobIdentifier}", [
            'job' => static::class,
            'queue' => $this->queue,
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * 任务执行后的处理
     */
    protected function afterExecute($result): void
    {
        Log::info("[Job Completed] {$this->jobIdentifier}", [
            'job' => static::class,
            'result' => $result,
        ]);
    }

    /**
     * 处理任务失败
     */
    protected function handleFailure(Throwable $e): void
    {
        if ($this->shouldLogFailure) {
            Log::error("[Job Failed] {$this->jobIdentifier}", [
                'job' => static::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 生成任务标识符
     */
    protected function generateJobIdentifier(): string
    {
        return sprintf(
            '%s:%s',
            class_basename($this),
            uniqid('job_')
        );
    }

    /**
     * 确定任务队列
     */
    protected function determineQueue(): string
    {
        return property_exists($this, 'queue') ? $this->queue : 'default';
    }

    /**
     * 设置任务重试次数
     */
    public function setTries(int $tries): self
    {
        $this->tries = $tries;
        return $this;
    }

    /**
     * 设置任务超时时间
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 设置重试间隔
     */
    public function setBackoff(int $backoff): self
    {
        $this->backoff = $backoff;
        return $this;
    }

    /**
     * 禁用失败日志
     */
    public function withoutFailureLogging(): self
    {
        $this->shouldLogFailure = false;
        return $this;
    }

    /**
     * 启用失败日志
     */
    public function withFailureLogging(): self
    {
        $this->shouldLogFailure = true;
        return $this;
    }

    /**
     * 获取任务标识符
     */
    public function getJobIdentifier(): string
    {
        return $this->jobIdentifier;
    }

    /**
     * 任务失败的处理
     */
    public function failed(Throwable $e): void
    {
        $this->handleFailure($e);
    }

    /**
     * 延迟执行任务
     */
    public function delay($delay): self
    {
        $this->delay = $delay;
        return $this;
    }

    /**
     * 在事务提交后分发任务
     */
    public static function dispatchAfterCommit(...$args): \Illuminate\Foundation\Bus\PendingDispatch
    {
        return static::dispatch(...$args)->afterCommit();
    }

    /**
     * 在事务回滚后分发任务
     */
    public static function dispatchAfterResponse(...$args): \Illuminate\Foundation\Bus\PendingDispatch
    {
        return static::dispatch(...$args)->afterResponse();
    }
}
