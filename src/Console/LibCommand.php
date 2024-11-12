<?php

namespace Ninex\Lib\Console;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Ninex\Lib\Traits\Database\WithDbTransaction;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Throwable;

/**
 * Class LibCommand
 * @package Ninex\Lib\Console
 */
abstract class LibCommand extends Command
{
    use WithDbTransaction;

    /**
     * 是否记录命令执行日志
     */
    protected bool $enableLogging = true;

    /**
    * 是否自动使用事务
    */
   protected bool $useTransaction = false;

    /**
     * 开始时间
     */
    protected float $startTime;

    /**
     * @var ProgressBar
     */
    protected $bar;

    /**
     * Laravel 的入口方法
     * 这个方法不应该被子类重写
     */
    final public function handle()
    {
        $this->startTime = microtime(true);

        try {
            if ($this->useTransaction) {
                $this->beforeTransaction(fn() => $this->beforeExecute())
                     ->afterTransaction(fn($result) => $this->afterExecute($result))
                     ->onTransactionError(fn($e) => $this->handleError($e));

                return $this->transaction(function () {
                    return $this->process();
                });
            }

            $this->beforeExecute();
            $result = $this->process();
            $this->afterExecute($result);
            return $result;

        } catch (Throwable $e) {
            $this->handleError($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * 实际的命令执行逻辑（子类必须实现）
     * @return int
     */
    abstract protected function process(): int;

    /**
     * 命令执行前的处理
     */
    protected function beforeExecute(): void
    {
        if ($this->enableLogging) {
            Log::info("[Command Started] {$this->getName()}", [
                'arguments' => $this->arguments(),
                'options' => $this->options(),
            ]);
        }

        $this->info('Starting command execution...');
    }

    /**
     * 命令执行后的处理
     */
    protected function afterExecute($result): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        if ($this->enableLogging) {
            Log::info("[Command Completed] {$this->getName()}", [
                'duration' => $duration,
                'result' => $result,
            ]);
        }

        $this->info("Command completed in {$duration}s");
    }

    /**
     * 处理命令执行错误
     */
    protected function handleError(Throwable $e): void
    {
        if ($this->enableLogging) {
            Log::error("[Command Failed] {$this->getName()}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 优雅地显示表格数据
     */
    protected function showTable(array $headers, array $rows): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)->setRows($rows);
        $table->render();
    }

    /**
     * 带进度条的批量处理
     * @param \Illuminate\Support\Collection|array|int $totalSteps
     * @param callable $callback
     * @return mixed
     */
    public function withProgressBar($totalSteps, callable $callback)
    {
        return parent::withProgressBar($totalSteps, $callback);
    }

    // 如果需要扩展进度条功能，可以添加新的方法
    protected function withCustomProgressBar(iterable $items, callable $callback): void
    {
        $count = is_countable($items) ? count($items) : null;
        $bar = $this->output->createProgressBar($count);

        foreach ($items as $item) {
            $callback($item);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * 确认用户输入（生产环境保护）
     */
    protected function confirmToProceed(string $warning = 'Application In Production!'): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if ($this->laravel->environment('production') &&
            !$this->confirm($warning . ' Do you really wish to run this command?')) {
            return false;
        }

        return true;
    }

    /**
     * 格式化大小显示
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    /**
     * 禁用日志记录
     */
    protected function withoutLogging(): self
    {
        $this->enableLogging = false;
        return $this;
    }

    /**
     * 启用日志记录
     */
    protected function withLogging(): self
    {
        $this->enableLogging = true;
        return $this;
    }

    /**
     * 分步执行任务
     */
    protected function step(string $description, callable $callback): void
    {
        $this->info("Step: {$description}");

        try {
            $callback();
            $this->info('✓ Done');
        } catch (Throwable $e) {
            $this->error('✗ Failed: ' . $e->getMessage());
            throw $e;
        }
    }


    /**
     * 创建一个进度条
     *
     * @param int $max
     */
    public function createBar(int $max = 0)
    {
        $this->bar = $this->output->createProgressBar($max);
    }

    /**
     * 当前进度条执步数
     *
     * @param int $step
     */
    public function advanceBar(int $step = 1)
    {
        $this->bar->advance($step);
    }

    /**
     * 为了换行
     *
     * @return void
     */
    public function finishBar(): void
    {
        $this->bar->finish();
        $this->output->newLine();
    }
}
