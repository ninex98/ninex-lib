<?php

namespace Ninex\Lib\Examples\Laravel;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Arr;
use Ninex\Lib\Http\Services\EloquentService;
use Ninex\Lib\Core\Page;

class ProductService extends EloquentService
{
    protected string $modelClass = Product::class;

    /** 列表。 / List records. */
    public function paginate(array $input = []): Page
    {
        return $this->getPage($input);
    }

    /** 详情。 / Show a record. */
    public function show(string|int $id): array
    {
        return $this->find($id);
    }

    /** 创建，基类统一处理验证和事务。 / Create with validation and a transaction. */
    public function store(array $input): array
    {
        return $this->create($input);
    }

    /** 更新，基类统一处理验证和事务。 / Update with validation and a transaction. */
    public function update(string|int $id, array $input): array
    {
        return $this->save($input, $id);
    }

    /** 删除。 / Delete a record. */
    public function destroy(string|int $id): void
    {
        $this->delete($id);
    }

    /** 表单验证，需要额外校验时在此扩展。 / Extend form validation here. */
    public function validateForm(array $data, string|int|null $id = null): array
    {
        return $this->validate($data, $this->rules($id));
    }

    /** 字段只在这里声明；id 为空表示创建。 / Declare fields once; a null ID means creation. */
    protected function rules(string|int|null $id = null): array
    {
        return [
            'name' => ($id === null ? 'required' : 'sometimes|required').'|string|max:100',
            'status' => 'sometimes|integer|in:0,1',
        ];
    }

    /** 在此添加模糊、范围等业务过滤。 / Add custom query filters here. */
    public function scopeQuery(Builder $query, array $conditions): void
    {
        $this->scopeWhereLike($query, Arr::only($conditions, ['name']));
        $this->scopeWhere($query, Arr::except($conditions, ['name']));
    }

    /** 保存前，可转换表单字段。 / Transform form fields before saving. */
    protected function saving(array &$data, string|int|null $id = null): void
    {
    }

    /** 保存后、提交前，关联写入使用同一连接。 / Write related data before commit on the same connection. */
    protected function saved(Model $record, bool $isEdit = false): void
    {
    }

    /** 删除后、提交前。 / After deletion, before commit. */
    protected function deleted(Model $record): void
    {
    }
}
