<?php

namespace Ninex\Lib\Traits\Database;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait WithDbTransaction
{
    /**
     * 事务执行前的回调
     */
    protected ?Closure $beforeTransactionHook = null;

    /**
     * 事务执行成功后的回调
     */
    protected ?Closure $afterTransactionHook = null;

    /**
     * 事务执行失败后的回调
     */
    protected ?Closure $errorTransactionHook = null;

    /**
     * 在事务中执行操作
     */
    protected function transaction(Closure $callback)
    {
        try {
            DB::beginTransaction();

            // 执行前置处理
            $this->runBeforeTransaction();

            $result = $callback();

            DB::commit();

            // 执行后置处理
            $this->runAfterTransaction($result);

            return $result;
        } catch (Throwable $e) {
            DB::rollBack();

            // 执行错误处理
            $this->runErrorTransaction($e);

            throw $e;
        }
    }

    /**
     * 设置事务执行前的回调
     */
    protected function beforeTransaction(Closure $callback): self
    {
        $this->beforeTransactionHook = $callback;
        return $this;
    }

    /**
     * 设置事务执行成功后的回调
     */
    protected function afterTransaction(Closure $callback): self
    {
        $this->afterTransactionHook = $callback;
        return $this;
    }

    /**
     * 设置事务执行失败后的回调
     */
    protected function onTransactionError(Closure $callback): self
    {
        $this->errorTransactionHook = $callback;
        return $this;
    }

    /**
     * 执行事务前的处理
     */
    private function runBeforeTransaction(): void
    {
        if ($this->beforeTransactionHook) {
            call_user_func($this->beforeTransactionHook);
        }
    }

    /**
     * 执行事务后的处理
     */
    private function runAfterTransaction($result): void
    {
        if ($this->afterTransactionHook) {
            call_user_func($this->afterTransactionHook, $result);
        }
    }

    /**
     * 执行事务错误处理
     */
    private function runErrorTransaction(Throwable $e): void
    {
        // 默认记录错误日志
        Log::error("[Transaction Failed]", [
            'class' => get_class($this),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->errorTransactionHook) {
            call_user_func($this->errorTransactionHook, $e);
        }
    }

    /**
     * 分批处理数据并使用事务
     *
     * @param iterable $items
     * @param Closure $callback
     * @param int $chunkSize
     * @return void
     * @throws Throwable
     */
    protected function chunkedTransaction(iterable $items, Closure $callback, int $chunkSize = 100): void
    {
        collect($items)->chunk($chunkSize)->each(function ($chunk) use ($callback) {
            $this->transaction(function () use ($chunk, $callback) {
                foreach ($chunk as $item) {
                    $callback($item);
                }
            });
        });
    }

    /**
     * 批量更新数据
     *
     * @param string $table
     * @param array $values
     * @param string $index
     * @return int
     */
    protected function batchUpdate(string $table, array $values, string $index = 'id'): int
    {
        if (empty($values)) {
            return 0;
        }

        $first = reset($values);
        $columns = array_keys($first);

        $cases = [];
        $ids = [];

        foreach ($values as $value) {
            $id = $value[$index];
            $ids[] = $id;

            foreach ($columns as $column) {
                if ($column === $index) {
                    continue;
                }
                $cases[$column][] = "WHEN {$id} THEN " . $this->quote($value[$column]);
            }
        }

        $cases = array_map(function ($column, $updates) use ($index) {
            $updates = implode(' ', $updates);
            return "{$column} = CASE {$index} {$updates} ELSE {$column} END";
        }, array_keys($cases), $cases);

        $ids = implode(',', $ids);
        $cases = implode(', ', $cases);

        return DB::update("UPDATE {$table} SET {$cases} WHERE {$index} IN ({$ids})");
    }

    /**
     * 批量插入并忽略重复
     *
     * @param string $table
     * @param array $values
     * @return bool
     */
    protected function insertIgnore(string $table, array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        return DB::table($table)->insertOrIgnore($values);
    }

    /**
     * 批量替换
     *
     * @param string $table
     * @param array $values
     * @return bool
     */
    protected function replace(string $table, array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        $first = reset($values);
        $columns = array_keys($first);

        $columnsString = implode(',', $columns);
        $valueStrings = [];

        foreach ($values as $value) {
            $valueStrings[] = '(' . implode(',', array_map([$this, 'quote'], $value)) . ')';
        }

        $valueString = implode(',', $valueStrings);
        $sql = "REPLACE INTO {$table} ({$columnsString}) VALUES {$valueString}";

        return DB::statement($sql);
    }

    /**
     * 锁表查询
     *
     * @param string|Model $table
     * @param Closure $callback
     * @return mixed
     */
    protected function withTableLock($table, Closure $callback)
    {
        if ($table instanceof Model) {
            $table = $table->getTable();
        }

        return DB::transaction(function () use ($table, $callback) {
            DB::statement("LOCK TABLES {$table} WRITE");
            try {
                $result = $callback();
                DB::statement('UNLOCK TABLES');
                return $result;
            } catch (Throwable $e) {
                DB::statement('UNLOCK TABLES');
                throw $e;
            }
        });
    }

    /**
     * 处理事务错误
     *
     * @param Throwable $e
     * @return void
     */
    protected function handleTransactionError(Throwable $e): void
    {
        // 可以在这里添加错误处理逻辑，比如日志记录
        if (method_exists($this, 'error')) {
            $this->error("Transaction failed: " . $e->getMessage());
        }
    }

    /**
     * 转义并引用值
     *
     * @param mixed $value
     * @return string
     */
    private function quote($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return $value;
        }

        return "'" . addslashes($value) . "'";
    }
}
