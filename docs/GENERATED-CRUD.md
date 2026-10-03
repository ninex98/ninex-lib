# 简洁业务 CRUD 开发指南

[返回接入指南](USAGE.md)

本文对应 2.1 起的简洁业务模板。生成器不会覆盖已有业务文件。

## 默认只维护业务

Controller 和 Service 都显式保留五个 CRUD 入口，每个入口默认只有一行 `$this->` 实例方法调用。验证、查询过滤、保存前后与删除后方法也直接出现在 Service 中，附简短中英文注释。

```php
public function update(string|int $id, array $input): array
{
    return $this->save($input, $id);
}

public function destroy(string|int $id): void
{
    $this->delete($id);
}
```

授权、记录查找、事务、钩子调用和响应字段处理统一由基类负责。业务文件不需要手动调用 `authorize()`、`persistDelete()`、`deleting()` 等底层步骤。

对应的实例调用是：`paginate → getPage`、`show → find`、`store → create`、`update → save`、`destroy → delete`。这些方法包含统一验证、权限和事务处理。`save($data)` 创建，`save($data, $id)` 更新；不要在 update 中写 `$this->update()`，否则会递归。新服务的 find 返回输出数组，与旧 LibService 返回 Model 的语义不同。

默认 Service 只声明模型类或表名，以及本模块的验证规则。只有指定了非默认 guard，生成器才额外写入 `$authGuard`。

| 要改什么 | 在业务 Service 中改哪里 |
|---|---|
| 某个接口的额外业务 | `paginate/show/store/update/destroy` 对应入口 |
| 字段验证规则 | `rules()` |
| 额外表单校验 | `validateForm()` |
| 模糊、范围、关联过滤 | `scopeQuery()` |
| 表单字段转换、保存前处理 | `saving()` |
| 保存关联表 | `saved()` |
| 删除关联数据 | `deleted()` |

`prepareSaveData()` 已移除；字段转换统一放在 `saving()`，避免为同一件事维护两套入口。

## 字段只声明一次

在 `rules()` 中声明字段及规则即可。基类默认推导：

- 输入字段：本次验证规则中的顶层字段。
- 输出、等值筛选和排序字段：规则字段与真实数据库字段的交集；默认包含主键输出和排序，排除归属字段。
- 保存字段：验证后的数据及 `saving()` 的业务转换结果。主键和归属字段不能通过普通保存数据修改。
- 排序：默认 `-id`；分页：默认每页 15、上限 100。

数据库字段在每个 Service 实例中最多读取一次元数据，以免把仅用于表单的别名当作数据库列。只输出规则声明的数据库字段，不会因为表里新增了内部字段就自动暴露它。

默认无需维护 `inputFields/writable/readable/filters/sorts/defaultSort/maxPageSize`。例如新加数据库字段和对应验证规则后，普通输入、输出与查询支持自动跟随。

## 表单转换

Laravel 的 `rules($id)` 与 ThinkPHP 的 `rules($data, $id)` 分别采用原生验证规则；`$id === null` 表示创建。ThinkPHP 的规则值为 null 表示允许本次空值而跳过类型验证，字段名仍然保留。

例如表单用 `display_name`，数据库用 `name`：修改 `rules()` 以验证 `display_name` 并调整 `name` 的必填条件，然后直接修改生成的 `saving()`：

```php
protected function saving(array &$data, string|int|null $id = null): void
{
    if (array_key_exists('display_name', $data)) {
        $data['name'] = trim($data['display_name']);
        unset($data['display_name']);
    }
}
```

基类会先验证一次，再执行转换并保存，不会对转换后的数据重复套用表单验证。转换后需要输出的数据库字段仍应在规则中声明，或按需定制 Resource。不要在调用 `$this->create()` / `$this->save()` 前再手动验证一次。

## 查询过滤

查询协议保持 `filter[status]=0&sort=-id&page=1&page_size=15`。基类处理分页和排序，`scopeQuery()` 处理业务条件。

下面的 Laravel 模糊查询只需要修改 scopeQuery，并引入 `Illuminate\Support\Arr`：

```php
public function scopeQuery(Builder $query, array $conditions): void
{
    $this->scopeWhereLike($query, Arr::only($conditions, ['name']));
    $this->scopeWhere($query, Arr::except($conditions, ['name']));
}
```

name 使用模糊匹配，其余字段使用等值匹配，不会为同一字段重复添加两种条件。scopeWhereLike 使用参数绑定，忽略 null 和空字符串，保留 0；scopeWhere 保留 0、false 和 null。两者都会校验真实字段，拒绝未允许的列名。

自定义 keyword 别名可传入 `$this->scopeWhereLike($query, ['name' => $conditions['keyword']])`，再从剩余条件中移除 keyword；无需再维护一份字段配置。范围查询可用 min_price/max_price 等参数配合原生 Builder 条件。ThinkPHP 提供同名实例辅助方法，数组选取可使用 array_intersect_key / array_diff_key。

权限范围与列表过滤各自分组，自定义 OR 过滤不会越过权限范围。默认 scopeQuery 是 WHERE 条件入口；Laravel 关联预加载等查询结构调整可按需覆盖基类的 `query()` 并在父查询上添加 `with()`。

## 按需使用的高级功能

基础能力保留，但不放进每个生成文件：

- 特殊操作权限：覆盖 `authorize()`；拒绝时抛出 403 业务异常。
- 默认按用户隔离数据。租户业务可修改 `$ownerColumn` 并覆盖 `ownerId()`；复杂数据范围覆盖 `scopeAccess()`。
- 服务端创建字段：覆盖 `creationDefaults()`。
- 删除前检查：按需添加 `deleting()`。
- 特殊字段、排序和分页限制：按需覆盖相应属性。

例如仅在确有需要时增加：

```php
protected ?array $readable = ['id', 'name'];
protected ?array $writable = ['name', 'status'];
protected int $maxPageSize = 50;
```

这些字段列表为 null 时使用自动默认值，空数组表示明确禁用全部对应字段。显式 `$filters` 会限制自定义别名，因此需要时自行包含别名；默认不设置它即可只在 scopeQuery 中扩展。验证规则始终生效，`inputFields` 不能代替验证。

默认 ProductService 示例只使用普通登录用户，不要求 tenant_id 或角色字段；owner_id 表示记录归属用户，不是租户。租户和角色权限仅为按需扩展能力。Laravel 使用 Model 作为钩子参数，ThinkPHP 使用完整记录数组。

## 事务与兼容

基类统一执行：授权与验证 → `saving()` → 数据库写入 → `saved()` → 数据范围复查 → 提交。删除执行授权 → `deleting()` → 删除 → `deleted()` → 提交。每个钩子只执行一次。

保存和删除钩子都发生在提交前，抛异常会回滚。关联写入须使用相同连接：Laravel 用 `$record->getConnection()`，ThinkPHP 用 `$this->db->connect($this->connection)`。外部消息应安排在提交后。Laravel 模型事件仍正常触发，应避免与 Service 钩子重复执行同一业务。

Laravel 由控制器 `callAction()` 在认证中间件之后解析 Service；ThinkPHP 由 `ServiceController::respond()` 统一处理服务与异常。业务 action 不重复初始化和开事务。继承自 LibController 的旧 CRUD 仍支持直接调用；新生成或自行覆写的 action 应经路由或 callAction 分发，以执行统一初始化。

旧 `LibService`、`GeneralHelpers`、`Core\CrudService` 及旧 ThinkPHP `CrudController` 保持可用。新模板使用 EloquentService / TableService，Controller 和 Service 的主要方法仍显式存在；它们对外返回数组或 Page，保持 HTTP 响应协议。

旧 LibService 的 `validateForm()` 返回 void，新模板返回验证后的数组；钩子签名也应按新模板移植。已生成的长流程 Service 可改为上述实例方法调用，把表单转换合并进 saving，并删去与默认值相同的配置。不要保留手动钩子调用，否则会重复执行。
