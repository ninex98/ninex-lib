<?php

namespace Ninex\Lib\Models;

use Illuminate\Database\Eloquent\Model;
use Ninex\Lib\Support\BulkWriter;

abstract class LibModel extends Model
{
    // Preserve the legacy model contract; HTTP input must be selected/validated by the application.
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime:Y-m-d H:i:s', 'updated_at' => 'datetime:Y-m-d H:i:s'];

    protected static function boot()
    {
        parent::boot();
        $invalidate = function (self $model) {
            if (config('ninexlib.model_cache.enabled', false)) {
                $key = $model->modelCacheKey($model->getKey());
                $model->getConnection()->afterCommit(function () use ($key) {
                    try {
                        cache()->forget($key);
                    } catch (\Throwable $e) {
                        // The database is already committed. A cache outage must not retry the write.
                        try {
                            report($e);
                        } catch (\Throwable) {
                        }
                    }
                });
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    protected static function transaction(callable $callback)
    {
        return (new static())->getConnection()->transaction($callback);
    }

    public static function insertIgnore(array $values)
    {
        return $values ? (new static())->newQuery()->insertOrIgnore($values) : true;
    }

    /** Bypasses model events, mutators and scopes; pass only trusted application data. */
    public static function batchUpdate(array $values, string $index)
    {
        if (!$values) {
            return true;
        }
        $model = new static();
        if (config('ninexlib.model_cache.enabled', false)) {
            throw new \LogicException('batchUpdate bypasses cache invalidation. Update individual models when model caching is enabled.');
        }
        return BulkWriter::update($model->getConnection(), $model->getTable(), $values, $index);
    }

    public static function massDelete(array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        $count = 0;
        (new static())->getConnection()->transaction(function () use ($ids, &$count) {
            static::query()->whereKey($ids)->chunkById(200, function ($models) use (&$count) {
                foreach ($models as $model) {
                    if ($model->delete()) {
                        $count++;
                    }
                }
            });
        });
        return $count;
    }

    /** Override with a server-derived tenant/visibility context if query scopes vary by request. */
    protected function cacheContext(): string
    {
        return '';
    }

    protected function modelCacheKey($id): string
    {
        $connection = $this->getConnection();
        return 'ninex:model:'.hash('sha256', json_encode([static::class, $connection->getName(), $connection->getDatabaseName(), $this->getTable(), $this->cacheContext(), (string) $id], JSON_THROW_ON_ERROR));
    }

    public static function findWithCache($id, $ttl = 3600)
    {
        $model = new static();
        if (!config('ninexlib.model_cache.enabled', false) || $model->getConnection()->transactionLevel() > 0) {
            return static::find($id);
        }
        // Keep database failures outside the cache exception boundary.
        try {
            $cached = cache()->get($model->modelCacheKey($id));
        } catch (\Throwable $e) {
            try {
                report($e);
            } catch (\Throwable) {
            } return static::find($id);
        }
        if ($cached !== null) {
            return $cached;
        }
        $result = static::find($id);
        if ($result !== null) {
            try {
                cache()->put($model->modelCacheKey($id), $result, $ttl);
            } catch (\Throwable $e) {
                try {
                    report($e);
                } catch (\Throwable) {
                }
            }
        }
        return $result;
    }

    public static function forgetCache($id): bool
    {
        return cache()->forget((new static())->modelCacheKey($id));
    }

    /** @deprecated Historical 1.x name: still returns totals. Use paginateWithoutTotal to skip COUNT. */
    public static function simplePaginate(array $where = [], array $order = [], int $perPage = 15)
    {
        return static::paginationQuery($where, $order, $perPage)->paginate($perPage);
    }

    public static function paginateWithoutTotal(array $where = [], array $order = [], int $perPage = 15)
    {
        return static::paginationQuery($where, $order, $perPage)->simplePaginate($perPage);
    }

    private static function paginationQuery(array $where, array $order, int $perPage)
    {
        if ($perPage < 1 || $perPage > config('ninexlib.pagination.max_page_size', 100)) {
            throw new \InvalidArgumentException('Invalid page size.');
        }
        $query = static::query()->where($where);
        foreach ($order as $key => $value) {
            $query->orderBy($key, $value);
        }
        return $query;
    }
}
