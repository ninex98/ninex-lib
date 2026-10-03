<?php

namespace Ninex\Lib\Http\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Ninex\Lib\Contracts\CrudActions;
use Ninex\Lib\Core\{Page, Query, ServiceException};
use Ninex\Lib\Support\CrudInput;

/** Eloquent 基础能力；封装公共 CRUD 流程，业务按需覆盖入口与钩子。 / Common CRUD workflow with optional business hooks. */
abstract class EloquentService implements CrudActions
{
    use CrudInput;

    /** 列表入口。 / List action. */
    public function paginate(array $input = []): Page
    {
        return $this->getPage($input);
    }

    /** 详情入口。 / Detail action. */
    public function show(string|int $id): array
    {
        return $this->find($id);
    }

    /** 创建入口。 / Create action. */
    public function store(array $input): array
    {
        return $this->create($input);
    }

    /** 更新入口。 / Update action. */
    public function update(string|int $id, array $input): array
    {
        return $this->save($input, $id);
    }

    /** 删除入口。 / Delete action. */
    public function destroy(string|int $id): void
    {
        $this->delete($id);
    }

    /** 列表：授权、数据范围、业务过滤、分页。 / Authorize, scope, filter and paginate. */
    public function getPage(array $input = []): Page
    {
        $this->authorize('index');
        $criteria = $this->queryCriteria($input);
        $query = $this->query();
        $query->where(fn (Builder $filters) => $this->scopeQuery($filters, $criteria->filters));
        return $this->paginateQuery($query, $criteria);
    }

    /** 详情：定位可见记录，再校验操作权限。 / Find a visible record and authorize access. */
    public function find(string|int $id): array
    {
        $record = $this->findRecord($id);
        $this->authorize('show', $record);
        return $this->presentRecord($record);
    }

    /** 创建：验证只执行一次；保存与钩子共用事务。 / Validate once; persist and run hooks in one transaction. */
    public function create(array $input): array
    {
        return $this->transaction(function () use ($input) {
            $this->authorize('store');
            $data = $this->validateForm($input);
            $this->saving($data);
            $record = $this->persistCreate($data);
            $this->saved($record, false);
            return $this->presentRecord($this->findRecord($record->getKey(), 403));
        });
    }

    /** 保存：无 id 时创建，否则更新；统一执行验证和事务。 / Create without an ID, otherwise update with validation and a transaction. */
    public function save(array $input, string|int|null $id = null): array
    {
        if ($id === null) {
            return $this->create($input);
        }
        return $this->transaction(function () use ($id, $input) {
            $record = $this->findRecord($id);
            $this->authorize('update', $record);
            $data = $this->validateForm($input, $id);
            $this->saving($data, $id);
            $record = $this->persistUpdate($record, $data);
            $this->saved($record, true);
            return $this->presentRecord($this->findRecord($id));
        });
    }

    /** 删除：前后钩子在事务内执行，异常会回滚。 / Delete and run hooks atomically. */
    public function delete(string|int $id): void
    {
        $this->transaction(function () use ($id) {
            $record = $this->findRecord($id);
            $this->authorize('destroy', $record);
            $this->deleting($record);
            $this->persistDelete($record);
            $this->deleted($record);
        });
    }

    /** @var class-string<Model> 模型类。 / Eloquent model class. */
    protected string $modelClass;
    protected ?string $authGuard = null;
    private ?array $columnNames = null;

    /** 只注入依赖，不在构造时读取身份。 / Inject dependencies without capturing identity. */
    public function __construct(protected Auth $auth, protected Validator $validator)
    {
    }

    /** 每次从当前 guard 读取身份。 / Resolve the current authenticated user. */
    protected function user(): Authenticatable
    {
        return $this->auth->guard($this->authGuard)->user() ?? throw new ServiceException('请先登录', 401);
    }

    /** 使用模型自己的连接、事件和转换器。 / Keep the model's connection, events and casts. */
    public function model(): Model
    {
        return new $this->modelClass();
    }

    /** 所有记录定位都应用数据权限。 / Apply access scope to every record query. */
    protected function query(): Builder
    {
        $query = $this->model()->newQuery();
        $query->where(fn (Builder $scope) => $this->scopeAccess($scope));
        return $query;
    }

    /** 业务定义数据可见范围。 / Define the business access scope. */
    protected function scopeAccess(Builder $query): void
    {
        if ($this->ownerColumn !== null) {
            $query->where($this->ownerColumn, $this->ownerId());
        }
    }

    /** 服务端创建字段，不接受客户端覆盖。 / Trusted creation defaults override client data. */
    protected function creationDefaults(): array
    {
        return $this->ownerColumn === null ? [] : [$this->ownerColumn => $this->ownerId()];
    }

    /** 读取完整模型用于授权和钩子，输出时才筛选字段。 / Load full records for authorization and hooks. */
    protected function findRecord(string|int $id, int $status = 404): Model
    {
        return $this->query()->find($id) ?? throw new ServiceException('数据不存在或不在允许的范围内', $status);
    }

    /** 已执行业务过滤的查询只查询当前页。 / Paginate a query after business filters are applied. */
    protected function paginateQuery(Builder $query, Query $criteria): Page
    {
        foreach ($criteria->sorts as $column => $direction) {
            $query->orderBy($column, $direction);
        }
        $page = $query->paginate($criteria->pageSize, ['*'], 'page', $criteria->page);
        return new Page(array_map($this->presentRecord(...), $page->items()), $page->total(), $page->perPage(), $page->currentPage());
    }

    /** 输出白名单，不泄露用于授权的内部字段。 / Restrict the public record to readable fields. */
    protected function presentRecord(Model $record): array
    {
        return $this->outputData($record->toArray());
    }

    /** 只持久化；不重复执行验证和 Service 钩子。 / Persist without invoking validation or service hooks. */
    protected function persistCreate(array $data): Model
    {
        $record = $this->model();
        $record->forceFill(array_merge($this->writeData($data), $this->creationDefaults()));
        if (!$record->save()) {
            throw new ServiceException('创建失败', 422);
        }
        return $this->findRecord($record->getKey(), 403);
    }

    /** 更新已授权模型；调用方负责事务和后续范围检查。 / Update an authorized model inside the caller's transaction. */
    protected function persistUpdate(Model $record, array $data): Model
    {
        if (!$record->forceFill($this->writeData($data))->save()) {
            throw new ServiceException('更新失败', 422);
        }
        return $this->findRecord($record->getKey());
    }

    /** 删除已授权模型，保留 Eloquent 事件。 / Delete an authorized model with Eloquent events. */
    protected function persistDelete(Model $record): void
    {
        if (!$record->delete()) {
            throw new ServiceException('删除失败', 422);
        }
    }

    /** 业务流程及钩子共用模型连接事务。 / Run workflow and hooks on the model's connection. */
    protected function transaction(callable $operation): mixed
    {
        return $this->model()->getConnection()->transaction($operation);
    }
    /** 验证一次，字段从规则推导。 / Validate once; infer form fields from rules. */
    public function validateForm(array $data, string|int|null $id = null): array
    {
        return $this->validate($data, $this->rules($id));
    }

    /** 使用显式规则验证表单。 / Validate form data against explicit rules. */
    protected function validate(array $data, array $rules): array
    {
        $this->assertInputFields($data, $rules);
        return $this->validator->make($data, $rules)->validate();
    }

    /** 业务验证规则，同时提供默认字段信息。 / Validation rules also define default field names. */
    abstract protected function rules(string|int|null $id = null): array;

    protected function fieldNames(): array
    {
        return $this->ruleFields($this->rules());
    }

    /** 当前实例只读取一次表字段，避免把表单别名用于排序。 / Load columns once per instance to exclude form aliases. */
    protected function databaseFields(): array
    {
        $model = $this->model();
        return $this->columnNames ??= $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
    }

    protected function primaryKey(): string
    {
        return $this->model()->getKeyName();
    }

    /** 默认身份归属；租户业务可覆盖。 / Override for tenant ownership. */
    protected function ownerId(): int
    {
        return $this->integerIdentity($this->user()->getAuthIdentifier());
    }

    /** 默认要求登录；特殊权限按需覆盖。 / Login by default; override for business permissions. */
    protected function authorize(string $operation, ?Model $record = null): void
    {
        $this->user();
    }

    /** 默认等值过滤；业务可在 scopeQuery 中处理其他条件。 / Apply declared field filters after custom conditions. */
    public function scopeQuery(Builder $query, array $conditions): void
    {
        $this->scopeWhere($query, $conditions);
    }

    /** 等值过滤，保留 0、false 和 null。 / Equality filters preserve zero, false and null. */
    public function scopeWhere(Builder $query, array $filters): void
    {
        $this->assertFilterFields($filters);
        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }
    }

    /** 模糊过滤，忽略空字符串，使用绑定参数。 / Apply LIKE filters with bound values, skipping empty strings. */
    public function scopeWhereLike(Builder $query, array $conditions): void
    {
        $this->assertFilterFields($conditions);
        foreach ($conditions as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_scalar($value)) {
                throw new ServiceException('模糊筛选值必须为标量', 422);
            }
            $query->where($field, 'like', '%'.$value.'%');
        }
    }

    /** 保存前转换字段，由公共流程调用一次。 / Transform fields once before persistence. */
    protected function saving(array &$data, string|int|null $id = null): void
    {
    }

    /** 保存后、提交前，异常会回滚。 / After persistence, before commit; failures roll back. */
    protected function saved(Model $record, bool $isEdit = false): void
    {
    }

    /** 可选删除前检查。 / Optional pre-delete check. */
    protected function deleting(Model $record): void
    {
    }

    /** 删除后、提交前，异常会回滚。 / After deletion, before commit; failures roll back. */
    protected function deleted(Model $record): void
    {
    }
}
