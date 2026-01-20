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
     * 模型类名（子类应该定义此属性）
     */
    protected ?string $modelClass = null;

    /**
     * 获取当前模型类名
     *
     * @return string
     */
    public function setModel(): string
    {
        // 如果子类定义了 modelClass，直接使用
        if ($this->modelClass && class_exists($this->modelClass)) {
            return $this->modelClass;
        }

        // 否则尝试自动推断
        $classNameArr = explode('\\', get_class($this));
        $modelName = substr(Arr::last($classNameArr), 0, -7);

        // 尝试多个命名空间
        $namespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($namespaces as $namespace) {
            $modelClass = $namespace . $modelName;
            if (class_exists($modelClass)) {
                return $modelClass;
            }
        }

        throw $this->createException('Model不存在，请在 Service 中定义 $modelClass 属性');
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
    public function query(): Builder
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
    public function find(
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
    public function findWhere(
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
    public function create(
        array    $data,
        ?string  $message = null,
        ?Builder $builder = null
    ): Model
    {
        $builder = $builder ?? $this->query();

        $this->saving($data);

        $result = $builder->create($data);

        if (!$result) {
            throw $this->createException(
                $message ?? '创建失败',
                422
            );
        }

        $this->saved($result);

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
    public function update(
        string   $id,
        array    $data,
        ?string  $message = null,
        ?Builder $builder = null
    ): Model
    {

        $this->saving($data,$id);

        $model = $this->find($id, $message, [], $builder);

        if (!$model->update($data)) {
            throw $this->createException(
                $message ?? '更新失败',
                422
            );
        }

        $this->saved($model);

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
    public function delete(
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

        $this->deleted($id);

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
    public function validateStore(array $data, string $message = '')
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
    public function validateUpdate(string $id, array $fields, string $message = '')
    {
        $this->validateForm($fields, $id);

        return $this->update($id, $fields, $message);
    }

    /**
     * 表单验证
     */
    public function validateForm(array $data, ?string $id = null): void
    {
        // 子类实现具体验证逻辑
    }

    /**
     * 分页查询
     */

    public function paginate(
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
    public function batchInsert(array $items, int $chunkSize = 100): bool
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
    public function scopeQuery(Builder $query, array $conditions)
    {
        foreach (array_filter(Arr::except($conditions, ['page', 'page_size'])) as $key => $value) {
            $query->where($key, $value);
        }
    }

    public function scopeWhere(Builder $query, array $conditions)
    {
        $this->scopeWhereCondition($query, $conditions, "=");
    }

    public function scopeWhereIn(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->whereIn($key, $value);
        }
    }

    public function scopeWhereLike(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->where($key, "like", "%{$value}%");
        }
    }

    public function scopeWhereBetween(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $value = is_string($value) ? explode(',', $value) : [];
            $query->whereBetween($key, [
                Carbon::parse(Arr::first($value))->startOfDay()->toDateTimeString(),
                Carbon::parse(Arr::last($value))->endOfDay()->toDateTimeString()
            ]);
        }
    }

    public function scopeWhereJsonContains(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->whereJsonContains($key, $value);
        }
    }

    public function scopeWhereInSet(Builder $query, array $conditions)
    {
        foreach (array_filter($conditions) as $key => $value) {
            $query->whereRaw('FIND_IN_SET(?, ' . $key . ')', [$value]);
        }
    }

    public function scopeWhereCondition(Builder $query, array $data, $condition)
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
    public function addAdvancedConditions(Builder $query, array $conditions): void
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

    /**
     * saving 钩子 (执行于新增/修改前)
     *
     * 可以通过判断 $primaryKey 是否存在来判断是新增还是修改
     *
     * @param $data
     * @param $primaryKey
     *
     * @return void
     */
    public function saving(&$data, $primaryKey = '')
    {

    }

    /**
     * saved 钩子 (执行于新增/修改后)
     *
     * 可以通过 $isEdit 来判断是新增还是修改
     *
     * @param $model
     * @param $isEdit
     *
     * @return void
     */
    public function saved($model, $isEdit = false)
    {

    }

    /**
     * deleted 钩子 (执行于删除后)
     *
     * @param $ids
     *
     * @return void
     */
    public function deleted($ids)
    {

    }

}
