<?php

namespace Ninex\Lib\Traits\Database;

use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait GeneralHelpers
{
    /**
     * 获取当前模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        $classNameArr = explode('\\', get_class($this));
        $modelName = substr(Arr::last($classNameArr), 0, -7);

        $modelClass = 'App\Models\\' . $modelName;
        if (!class_exists($modelClass)) {
            throw $this->createException('Model不存在：' . $modelClass);
        }

        return $modelClass;
    }

    /**
     * 获取模型实例
     * @throws BindingResolutionException
     */
    public function model(): Model
    {
        return app()->make($this->setModel());
    }

    /**
     * 获取查询构造器
     */
    protected function query(): Builder
    {
        return $this->model()->newQuery();
    }

    /**
     * 查找单个记录
     *
     * @param string $id ID
     * @param string|null $message 错误信息
     * @param array $with 关联关系
     * @param Builder|null $builder 查询构造器
     */
    protected function find(
        string   $id,
        ?string  $message = null,
        array    $with = [],
        ?Builder $builder = null
    ): Model
    {
        $builder = $builder ?? $this->query();

        $result = $builder->with($with)->find($id);

        if (!$result) {
            throw $this->createException(
                $message ?? '数据不存在',
                404
            );
        }

        return $result;
    }

    /**
     * 根据条件查找单个记录
     *
     * @param array $conditions 查询条件
     * @param string|null $message 错误信息
     * @param array $with 关联关系
     * @param Builder|null $builder 查询构造器
     */
    protected function findWhere(
        array    $conditions,
        ?string  $message = null,
        array    $with = [],
        ?Builder $builder = null
    ): Model
    {
        $builder = $builder ?? $this->query();

        $result = $builder
            ->with($with)
            ->where($conditions)
            ->first();

        if (!$result) {
            throw $this->createException(
                $message ?? '数据不存在',
                404
            );
        }

        return $result;
    }

    /**
     * 创建记录
     *
     * @param array $data 创建数据
     * @param string|null $message 错误信息
     * @param Builder|null $builder 查询构造器
     */
    protected function create(
        array    $data,
        ?string  $message = null,
        ?Builder $builder = null
    ): Model
    {
        $builder = $builder ?? $this->query();

        $result = $builder->create($data);

        if (!$result) {
            throw $this->createException(
                $message ?? '创建失败',
                422
            );
        }

        return $result;
    }

    /**
     * 更新记录
     *
     * @param string $id 更新模型ID
     * @param array $data 更新数据
     * @param string|null $message 提示信息
     * @param Builder|null $builder QueryBuilder
     * @return Model
     */
    protected function update(
        string   $id,
        array    $data,
        ?string  $message = null,
        ?Builder $builder = null
    ): Model
    {
        $model = $this->find($id, $message, [], $builder);

        if (!$model->update($data)) {
            throw $this->createException(
                $message ?? '更新失败',
                422
            );
        }

        return $model;
    }

    /**
     * 删除记录
     *
     * @param string $id
     * @param string|null $message
     * @param Builder|null $builder
     * @return bool
     */
    protected function delete(
        string   $id,
        ?string  $message = null,
        ?Builder $builder = null
    ): bool
    {
        $model = $this->find($id, $message, [], $builder);

        if (!$model->delete()) {
            throw $this->createException(
                $message ?? '删除失败',
                422
            );
        }

        return true;
    }

    /**
     * 创建新记录
     *
     * @param array $data
     * @param string $message
     * @return Model
     */
    public function store(array $data, string $message = ''): Model
    {
        return $this->create($data, $message);
    }

    /**
     * 显示指定记录
     *
     * @param string $id 记录ID
     * @param string $message 自定义错误消息
     * @param array $with 需要预加载的关联关系
     * @return Model 返回查询到的模型实例
     */
    public function show(string $id, string $message = '', array $with = []): Model
    {
        return $this->find($id, $message, $with);
    }

    /**
     * 删除指定记录
     *
     * @param string $id
     * @param string|null $message
     * @return bool
     */
    public function destroy(string $id, ?string $message = null): bool
    {
        return $this->delete($id, $message);
    }

    /**
     * 验证创建
     * @param array $data
     * @param string $message
     * @return Model
     * @throws \Exception
     */
    protected function validateStore(array $data, string $message = '')
    {
        $this->validateForm($data);

        return $this->create($data, $message);
    }

    /**
     * 验证更新
     * @param $id
     * @param $fields
     * @throws \Exception
     */
    protected function validateUpdate(string $id, array $fields, string $message = '')
    {
        $this->validateForm($fields, $id);

        return $this->update($id, $fields, $message);
    }

    /**
     * 表单验证
     */
    protected function validateForm(array $data, ?string $id = null): void
    {
        // 子类实现具体验证逻辑
    }

    /**
     * 分页查询
     */

    protected function paginate(
        array    $conditions = [],
        array    $with = [],
        array    $orderBy = ['id' => 'desc'],
        ?Builder $builder = null
    )
    {
        $builder = $builder ?? $this->query();
        $query = $builder->with($with);

        // 添加查询条件
        $this->scopeQuery($query, $conditions);

        // 添加排序
        foreach ($orderBy ?: [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }
        $perPage = $conditions['page_size'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * 获取所有记录
     */
    public function all(
        array    $conditions = [],
        array    $with = [],
        array    $orderBy = ['id' => 'desc'],
        ?Builder $builder = null
    )
    {
        $builder = $builder ?? $this->query();
        $query = $builder->with($with);

        // 添加查询条件
        $this->scopeQuery($query, $conditions);

        // 添加排序
        foreach ($orderBy ?: [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->get();
    }

    /**
     * 批量插入（智能分块）
     */
    protected function batchInsert(array $items, int $chunkSize = 100): bool
    {
        if (empty($items)) {
            return false;
        }

        $chunks = array_chunk($items, $chunkSize);

        foreach ($chunks as $chunk) {
            $this->model()->insert($chunk);
        }

        return true;
    }

    /**
     * @param Builder $query
     */
    protected function scopeQuery(Builder $query, array $conditions)
    {
        foreach (array_filter(Arr::except($conditions, ['page', 'page_size'])) as $key => $value) {
            $query->where($key, $value);
        }
    }

    protected function scopeWhere(Builder $query, array $conditions)
    {
        $this->scopeWhereCondition($query, $conditions, "=");
    }

    protected function scopeWhereIn(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->whereIn($key, $value);
        }
    }

    protected function scopeWhereLike(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->where($key, "like", "%{$value}%");
        }
    }

    protected function scopeWhereBetween(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $value = is_string($value) ? explode(',', $value) : [];
            $query->whereBetween($key, [
                Carbon::parse(Arr::first($value))->startOfDay()->toDateTimeString(),
                Carbon::parse(Arr::last($value))->endOfDay()->toDateTimeString()
            ]);
        }
    }

    protected function scopeWhereJsonContains(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->whereJsonContains($key, $value);
        }
    }

    protected function scopeWhereInSet(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->whereRaw('FIND_IN_SET(?, ' . $key . ')', [$value]);
        }
    }

    protected function scopeWhereCondition(Builder $query, array $data, $condition)
    {
        foreach (array_filter($data, function ($var) {
            return (($var !== null) && ($var !== ""));
        }) as $key => $value) {
            $query->where($key, $condition, $value);
        }
    }

    /**
     * 添加高级查询条件
     */
    protected function addAdvancedConditions(Builder $query, array $conditions): void
    {
        // 精确匹配
        if (isset($conditions['where']) && is_array($conditions['where'])) {
            $this->scopeWhere($query, $conditions['where']);
        }

        // IN 查询
        if (isset($conditions['whereIn']) && is_array($conditions['whereIn'])) {
            $this->scopeWhereIn($query, $conditions['whereIn']);
        }

        // LIKE 查询
        if (isset($conditions['whereLike']) && is_array($conditions['whereLike'])) {
            $this->scopeWhereLike($query, $conditions['whereLike']);
        }

        // 时间范围查询
        if (isset($conditions['whereBetween']) && is_array($conditions['whereBetween'])) {
            $this->scopeWhereBetween($query, $conditions['whereBetween']);
        }

        // JSON 包含查询
        if (isset($conditions['whereJsonContains']) && is_array($conditions['whereJsonContains'])) {
            $this->scopeWhereJsonContains($query, $conditions['whereJsonContains']);
        }

        // FIND_IN_SET 查询
        if (isset($conditions['whereInSet']) && is_array($conditions['whereInSet'])) {
            $this->scopeWhereInSet($query, $conditions['whereInSet']);
        }
    }

}
