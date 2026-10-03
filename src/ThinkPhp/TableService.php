<?php

namespace Ninex\Lib\ThinkPhp;

use Ninex\Lib\Contracts\CrudActions;
use Ninex\Lib\Core\{Page, Query as Criteria, ServiceException};
use Ninex\Lib\Support\CrudInput;
use think\{DbManager, Request, Validate};
use think\db\Query;

/** ThinkORM 查询基础能力；使用 Service 钩子，不模拟模型事件。 / Query persistence with service hooks, not model events. */
abstract class TableService implements CrudActions
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
        $query->where(fn (Query $filters) => $this->scopeQuery($filters, $criteria->filters));
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
            return $this->presentRecord($this->findRecord($record[$this->primaryKey], 403));
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

    protected string $table;
    protected string $primaryKey = 'id';
    protected ?string $connection = null;
    private ?array $columnNames = null;

    /** 仅注入依赖；身份由中间件提供。 / Inject dependencies; middleware supplies identity. */
    public function __construct(protected DbManager $db, protected Request $request)
    {
    }

    /** 从可信中间件读取当前身份。 / Read identity from trusted middleware, never form input. */
    protected function actor(): array
    {
        $actor = $this->request->middleware('actor');
        if (!is_array($actor) || !isset($actor['id'])) {
            throw new ServiceException('请先登录', 401);
        }
        return $actor;
    }

    /** 所有定位查询都应用数据权限。 / Apply access scope to every record query. */
    protected function query(): Query
    {
        foreach ([$this->table, $this->primaryKey] as $name) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
                throw new \LogicException('Invalid table or primary key.');
            }
        }
        $query = $this->db->connect($this->connection)->table($this->table)->pk($this->primaryKey);
        $query->where(fn (Query $scope) => $this->scopeAccess($scope));
        return $query;
    }

    /** 业务定义数据可见范围。 / Define the business access scope. */
    protected function scopeAccess(Query $query): void
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

    /** 内部保留完整记录，响应时再过滤字段。 / Load full records for authorization and hooks. */
    protected function findRecord(string|int $id, int $status = 404): array
    {
        return $this->query()->where($this->primaryKey, '=', $id)->find() ?? throw new ServiceException('数据不存在或不在允许的范围内', $status);
    }

    /** 分页保持统一响应结构。 / Paginate a filtered native query with the common page format. */
    protected function paginateQuery(Query $query, Criteria $criteria): Page
    {
        $total = (clone $query)->count();
        foreach ($criteria->sorts as $column => $direction) {
            $query->order($column, $direction);
        }
        $rows = $query->page($criteria->page, $criteria->pageSize)->select()->toArray();
        return new Page(array_map($this->presentRecord(...), $rows), $total, $criteria->pageSize, $criteria->page);
    }

    /** 输出白名单。 / Restrict the public record to readable fields. */
    protected function presentRecord(array $record): array
    {
        return $this->outputData($record);
    }

    /** 只持久化，不重复验证或调用钩子。 / Persist without validation or service hooks. */
    protected function persistCreate(array $data): array
    {
        $id = $this->query()->insertGetId(array_merge($this->writeData($data), $this->creationDefaults()));
        return $this->findRecord($id, 403);
    }

    /** 更新后重新检查数据范围。 / Recheck access scope after updating. */
    protected function persistUpdate(array $record, array $data): array
    {
        $data = $this->writeData($data);
        if ($data) {
            $this->query()->where($this->primaryKey, '=', $record[$this->primaryKey])->update($data);
        }
        return $this->findRecord($record[$this->primaryKey]);
    }

    /** 在数据范围内删除记录。 / Delete within the access scope. */
    protected function persistDelete(array $record): void
    {
        if (!$this->query()->where($this->primaryKey, '=', $record[$this->primaryKey])->delete()) {
            throw new ServiceException('数据不存在', 404);
        }
    }

    /** 钩子和写入必须使用同一连接。 / Run workflow and hooks on the persistence connection. */
    protected function transaction(callable $operation): mixed
    {
        return $this->db->connect($this->connection)->transaction($operation);
    }
    /** 验证一次，允许空值的规则使用 null 标记。 / Validate once; null rules mark accepted nullable values. */
    public function validateForm(array $data, string|int|null $id = null): array
    {
        return $this->validate($data, $this->rules($data, $id));
    }

    /** 使用显式规则验证表单。 / Validate form data against explicit rules. */
    protected function validate(array $data, array $rules): array
    {
        $this->assertInputFields($data, $rules);
        $validator = new Validate();
        if (!$validator->rule(array_filter($rules, fn ($rule) => $rule !== null))->batch(true)->check($data)) {
            throw new ServiceException('数据验证失败', 422, ['errors' => $validator->getError()]);
        }
        return array_intersect_key($data, array_flip($this->ruleFields($rules)));
    }

    /** 业务验证规则，同时提供默认字段信息。 / Validation rules also define default field names. */
    abstract protected function rules(array $data, string|int|null $id = null): array;

    protected function fieldNames(): array
    {
        return $this->ruleFields($this->rules([], null));
    }

    /** 当前实例只读取一次表字段，避免把表单别名用于排序。 / Load columns once per instance to exclude form aliases. */
    protected function databaseFields(): array
    {
        return $this->columnNames ??= $this->db->connect($this->connection)->getTableFields($this->table);
    }

    protected function primaryKey(): string
    {
        return $this->primaryKey;
    }

    /** 默认身份归属；租户业务可覆盖。 / Override for tenant ownership. */
    protected function ownerId(): int
    {
        return $this->integerIdentity($this->actor()['id']);
    }

    /** 默认要求登录；特殊权限按需覆盖。 / Login by default; override for business permissions. */
    protected function authorize(string $operation, ?array $record = null): void
    {
        $this->actor();
    }

    /** 默认等值过滤；业务可在 scopeQuery 中处理其他条件。 / Apply declared field filters after custom conditions. */
    public function scopeQuery(Query $query, array $conditions): void
    {
        $this->scopeWhere($query, $conditions);
    }

    /** 等值过滤，保留 0、false 和 null。 / Equality filters preserve zero, false and null. */
    public function scopeWhere(Query $query, array $filters): void
    {
        $this->assertFilterFields($filters);
        foreach ($filters as $field => $value) {
            $value === null ? $query->whereNull($field) : $query->where($field, '=', $value);
        }
    }

    /** 模糊过滤，忽略空字符串，使用绑定参数。 / Apply LIKE filters with bound values, skipping empty strings. */
    public function scopeWhereLike(Query $query, array $conditions): void
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
    protected function saved(array $record, bool $isEdit = false): void
    {
    }

    /** 可选删除前检查。 / Optional pre-delete check. */
    protected function deleting(array $record): void
    {
    }

    /** 删除后、提交前，异常会回滚。 / After deletion, before commit; failures roll back. */
    protected function deleted(array $record): void
    {
    }
}
