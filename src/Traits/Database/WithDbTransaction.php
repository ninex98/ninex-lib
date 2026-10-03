<?php

namespace Ninex\Lib\Traits\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ninex\Lib\Support\BulkWriter;
use Throwable;

trait WithDbTransaction
{
    protected ?Closure $beforeTransactionHook = null;
    protected ?Closure $afterTransactionHook = null;
    protected ?Closure $errorTransactionHook = null;

    protected function transactionConnection(): Connection
    {
        return DB::connection();
    }

    protected function transaction(Closure $callback)
    {
        $connection = $this->transactionConnection();
        $before = $this->beforeTransactionHook;
        $after = $this->afterTransactionHook;
        $error = $this->errorTransactionHook;
        $this->beforeTransactionHook = $this->afterTransactionHook = $this->errorTransactionHook = null;
        try {
            return $connection->transaction(function () use ($callback, $connection, $before, $after) {
                if ($before) {
                    $before();
                }
                $result = $callback();
                if ($after) {
                    $connection->afterCommit(function () use ($after, $result) {
                        try {
                            $after($result);
                        } catch (Throwable $exception) {
                            // Already committed. Reporting must not make a queue retry committed writes.
                            try {
                                report($exception);
                            } catch (Throwable) {
                            }
                        }
                    });
                }
                return $result;
            });
        } catch (Throwable $exception) {
            try {
                Log::error('[Transaction Failed]', ['class' => static::class, 'exception' => $exception]);
                if ($error) {
                    $error($exception);
                }
            } catch (Throwable $reportingError) {
                try {
                    report($reportingError);
                } catch (Throwable) {
                }
            }
            throw $exception;
        }
    }

    protected function beforeTransaction(Closure $callback): self
    {
        $this->beforeTransactionHook = $callback;
        return $this;
    }
    protected function afterTransaction(Closure $callback): self
    {
        $this->afterTransactionHook = $callback;
        return $this;
    }
    protected function onTransactionError(Closure $callback): self
    {
        $this->errorTransactionHook = $callback;
        return $this;
    }

    protected function chunkedTransaction(iterable $items, Closure $callback, int $chunkSize = 100): void
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('chunkSize must be positive.');
        }
        $chunk = [];
        $flush = function (array $items) use ($callback) {
            $this->transaction(function () use ($items, $callback) {
                foreach ($items as $item) {
                    $callback($item);
                }
            });
        };
        foreach ($items as $item) {
            $chunk[] = $item;
            if (count($chunk) === $chunkSize) {
                $flush($chunk);
                $chunk = [];
            }
        }
        if ($chunk) {
            $flush($chunk);
        }
    }

    protected function batchUpdate(string $table, array $values, string $index = 'id'): int
    {
        return BulkWriter::update($this->transactionConnection(), $table, $values, $index);
    }

    protected function insertIgnore(string $table, array $values): bool
    {
        BulkWriter::identifier($table);
        if (!$values) {
            return true;
        }
        $this->transactionConnection()->table($table)->insertOrIgnore($values);
        return true;
    }

    /** REPLACE may delete and reinsert existing rows; it is not an update. */
    protected function replace(string $table, array $values): bool
    {
        return BulkWriter::replace($this->transactionConnection(), $table, $values);
    }

    /** @deprecated Table locks cannot safely be composed with application transactions. */
    protected function withTableLock($table, Closure $callback)
    {
        throw new \LogicException('withTableLock was removed for transaction safety. Use withRowLock with an explicit query.');
    }

    protected function withRowLock(Builder $query, Closure $callback)
    {
        return $query->getModel()->getConnection()->transaction(fn () => $callback($query->lockForUpdate()->get()));
    }

    protected function handleTransactionError(Throwable $e): void
    {
    }
}
