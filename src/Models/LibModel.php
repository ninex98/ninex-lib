<?php

namespace Ninex\Lib\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class LibModel extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function boot()
    {
        parent::boot();

        // 如果你不希望 Laravel 自动维护 created_at 和 updated_at 字段，可以取消以下行的注释
        // static::unsetEventDispatcher();
    }

    /**
     * 安全的事务处理
     * @throws \Throwable
     */
    protected static function transaction(callable $callback)
    {
        try {
            DB::beginTransaction();
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 批量插入并忽略重复
     */
    public static function insertIgnore(array $values)
    {
        if (empty($values)) {
            return true;
        }

        $table = (new static)->getTable();

        return DB::table($table)->insertOrIgnore($values);
    }

    /**
     * 批量更新
     */
    public static function batchUpdate(array $values, string $index)
    {
        if (empty($values)) {
            return true;
        }

        $table = (new static)->getTable();
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
                $cases[$column][] = "WHEN {$id} THEN '{$value[$column]}'";
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
     * 批量删除并触发模型事件
     */
    public static function massDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $models = static::whereIn((new static)->getKeyName(), $ids)->get();

        $count = 0;
        foreach ($models as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 获取单条记录，支持缓存
     */
    public static function findWithCache($id, $ttl = 3600)
    {
        $key = static::class . ':' . $id;

        return cache()->remember($key, $ttl, function () use ($id) {
            return static::find($id);
        });
    }

    /**
     * 清除指定ID的缓存
     * @throws \Exception
     */
    public static function forgetCache($id): bool
    {
        $key = static::class . ':' . $id;
        return cache()->forget($key);
    }

    /**
     * 分页查询的简单封装
     */
    public static function simplePaginate(array $where = [], array $order = [], int $perPage = 15)
    {
        $query = static::query();

        foreach ($where as $key => $value) {
            $query->where($key, $value);
        }

        foreach ($order as $key => $value) {
            $query->orderBy($key, $value);
        }

        return $query->paginate($perPage);
    }
}
