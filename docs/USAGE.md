# 接入指南

[返回 README](../README.md)

> 本页描述 2.1 的简洁 Service 模板。简洁模板、扩展入口和迁移方式见[业务 CRUD](GENERATED-CRUD.md)。

## Laravel

在已有 Laravel 应用中安装 `ninex/lib` 后，Provider 自动注册命令、合并配置并加载生成的路由。
默认配置可直接使用；仅在需要修改时执行 `php artisan ninexlib:install` 发布 `config/ninexlib.php`。

```bash
php artisan ninexlib:doctor
php artisan ninexlib:make-crud Product --fields="name:string,status:boolean,note:text?" --dry-run
php artisan ninexlib:make-crud Product --fields="name:string,status:boolean,note:text?"
php artisan migrate
```

| 生成文件 | 职责 |
|---|---|
| `app/Models/Product.php` | 数据模型 |
| `app/Services/ProductService.php` | 验证、权限、字段与查询范围 |
| `app/Http/Controllers/ProductController.php` | 调用服务并统一响应 |
| `database/migrations/*_create_products_table.php` | 表结构与所有权索引 |
| `routes/ninex/products.php` | CRUD 路由，默认挂载到 `/api/products` |

认证使用宿主默认 guard，可通过 `--guard=sanctum` 等选择应用已配置的 guard。本包不创建用户表或登录系统。
服务由基类的 `callAction` 在认证中间件之后、业务方法之前统一解析，不应注册为跨请求 singleton。配置了服务的自定义 action 也使用这个入口，无需逐个调用 `prepareCrud()`。

生成的业务控制器显式包含 `index`、`show`、`store`、`update`、`destroy` 五个方法，可直接在对应入口添加业务逻辑。每个方法调用 Service 并返回统一响应；分页资源转换和创建状态码由基类封装，验证、授权、钩子调度和事务由 Service 基类统一处理。新 action 不额外包装控制器事务。
生成器不会覆盖已有文件；已生成的控制器仍可继续继承基类方法，需要显式入口时手动合并新版模板。
自行覆盖 `callAction` 时应调用父方法；控制器测试优先通过 HTTP 路由执行，直接调用自行覆写的 action 会跳过统一初始化；继承的旧 CRUD 仍保留直接调用兼容。已有 action 内的 `prepareCrud()` 可移除，避免重复解析服务。

路由前缀、中间件和自动加载开关由 `ninexlib.routes` 配置控制。Artisan 生成命令会清理现有路由缓存；部署时可重新执行 `php artisan route:cache`。
生成路由始终使用 JSON 响应，包括未携带 `Accept` 头的认证错误。

`LibClient` 是 Laravel 的可选 HTTP 封装。使用它时确保宿主安装了 Guzzle 7：

```bash
composer require guzzlehttp/guzzle:^7.0
```

## ThinkPHP

在已有 ThinkPHP 应用中安装的同样是 `ninex/lib`。
标准项目的 Composer 服务发现会注册原生命令；如果宿主关闭了 Composer scripts，先执行一次 `php think service:discover`。

```bash
php think ninexlib:doctor
php think ninexlib:make-crud Product --fields="name:string,status:boolean,note:text?"
```

生成 `app/service/ProductService.php`、`app/controller/ProductController.php`、`route/ninex_products.php` 和 `database/ninex/` 下的两份建表 SQL。
选择 MySQL 或 SQLite 对应的 SQL 执行即可，不要重复执行两份文件。默认路由为 `/api/products`。

认证中间件应从可信 session/token 获取身份，通过 `$request->withMiddleware(['actor' => $actor])` 提供 `actor[id]`；不要从客户端提交字段取得身份。
生成控制器在每次 action 内创建服务，并直接处理统一 JSON 异常，无需修改 `app/provider.php`。
业务控制器显式包含 `index`、`read`、`save`、`update`、`delete` 五个方法，服务调用保留在 `respond` 回调内，以统一处理身份解析、响应和异常。

对于生成 CRUD 以外的 API，可以把下面的绑定合并到现有 `app/provider.php`，或在自定义处理器中整合：

```php
return [\think\exception\Handle::class => \Ninex\Lib\ThinkPhp\ExceptionHandler::class];
```

不要覆盖已有 provider 文件。ThinkORM 接入使用原生查询构造器，不自动执行模型事件或软删除；需要这些语义时使用业务自己的模型适配。旧公共核心仍可通过自定义 `CrudRepository` 接入。

## 旧 Laravel 基类

现有 `LibModel` 保留 `protected $guarded = ['id']`，不要求为每个模型新增 `$fillable`。已有 `$fillable` 仍由 Eloquent 执行。
应用应在业务层使用验证结果或明确的字段提取控制写入内容，避免将未经筛选的请求直接传给模型。

| 旧 Service 方法 | 验证与保存 |
|---|---|
| `create` / `store` / `update` | 保存数据，运行 saving/saved 钩子，不自动调用表单验证 |
| `validateStore` / `validateUpdate` | 调用一次 validateForm，再保存 |
| `validateForm` | 可选钩子；子类未覆盖时为空操作 |

已有业务可以先验证请求，再把分类数组、关联参数等转换成数据库字段，最后调用 create/update。转换后的数据不会重新按请求规则验证。
CRUD 保存和钩子继续使用模型所在连接的事务，失败会回滚。

旧控制器默认沿用应用已有的权限中间件和服务逻辑。需要自动调用 Laravel Gate/Policy 时，在使用旧 Service 的控制器中显式启用：

```php
protected bool $usePolicy = true;
```

对应能力为 `viewAny`、`view`、`create`、`update`、`delete`。注册 Policy 或 Gate 规则后执行检查；启用但没有匹配规则时返回拒绝，不会自动放行。
开关作用于基类提供的 CRUD 方法；自行覆写的 action 仍应在自身或 Service 中执行授权。
核心 Service 和新的业务 Service 实现 `CrudActions`，自行执行授权，不使用此旧控制器开关；生成资源的登录和所有权隔离不受它影响。

## 字段与生成选项

| 字段类型 | 示例 | 约束 |
|---|---|---|
| `string` | `name:string` | 最多 255 字符 |
| `text` | `description:text?` | 最多 65535 字符 |
| `integer` | `quantity:integer` | 有符号 32 位范围 |
| `boolean` | `active:boolean` | 布尔值 / 0 / 1 |
| `date` | `published_on:date?` | `Y-m-d` |
| `datetime` | `published_at:datetime?` | `Y-m-d H:i:s` |

类型后加 `?` 表示可空，也可在创建时省略。非空字段创建时必填，更新时只校验提交字段。
最多支持 32 个字段；`id`、`owner_id`、`created_at`、`updated_at` 为保留名称。

| 选项 | 作用 |
|---|---|
| `--fields` | 必填，逗号分隔的字段定义 |
| `--table` / `--route` | 自定义表名 / 路由资源名；不规则复数建议显式指定 |
| `--guard` | 选择 Laravel 已配置的认证 guard |
| `--dry-run` | 预览生成文件，不写入磁盘 |
| `--framework` | 独立 CLI 指定 `laravel` / `thinkphp`，省略时自动识别 |
| `--namespace` / `--path` | 独立 CLI 指定应用命名空间 / 项目目录 |

```bash
vendor/bin/ninex make:crud Category --framework=laravel --fields="name:string,active:boolean" --table=categories --route=categories --namespace=App --path=/path/to/app --dry-run
```

独立 CLI 支持 `--key=value` 与 `--key value`，不启动宿主应用。使用它生成后，已有路由缓存需要通过宿主命令清理。
项目使用 authoritative classmap 时，新增类后还需重新生成 Composer autoload。

生成器检查标识符、PHP 语法、文件冲突与符号链接目录，不覆盖已有文件。
字段定义与模板只在生成时使用，运行时直接执行应用代码。模板位于 `resources/stubs`。

## 权限与查询

这里的“需要登录”指调用生成接口的业务用户：请求须携带项目认可的 Cookie、Token 等身份凭据，服务端据此识别当前用户。安装扩展包、执行代码生成命令不需要登录；本包也不提供注册、登录接口或用户表。

以下是生成模板默认采用的“用户私有数据”规则，不是对整个应用所有接口的统一限制。未能识别用户时，生成的 Service 返回 401；通过认证后，仅允许访问 `owner_id` 等于当前用户 ID 的记录。例如，用户 A 创建的记录，用户 B 无法在列表中看到，也无法通过记录 ID 查看、修改或删除。
`owner_id` 由服务器写入，客户端不能提交，响应也不暴露该字段。模板使用整数用户 ID，UUID 或其他身份类型需要调整表结构和服务。

Laravel 模板通过应用已有的认证 guard 获取用户，生成路由挂载对应认证中间件；ThinkPHP 模板从应用认证中间件提供的 `actor[id]` 获取用户身份。已有登录系统可以直接接入，具体方式见上面的框架接入说明。

公开查询、管理员管理或团队共享需要不同的数据权限。按需覆盖 Service 的 `authorize()`、`scopeAccess()`、`creationDefaults()`，并调整路由上的认证中间件；改变数据归属规则时，还需同步调整 `owner_id` 的写入逻辑与表结构。仅移除路由认证中间件不会取消 Service 内的身份检查。当前生成器没有公开接口或管理员模式的一键开关。

默认只维护 `rules()` 中的验证规则和 `scopeQuery()` 中的业务过滤，不需要重复声明多份字段名单。基类自动处理字段默认值、授权、事务及保存/删除钩子的调用。

| 修改目标 | 入口 |
|---|---|
| 表单规则 | `rules()` / `validateForm()` |
| 表单到数据库字段的转换 | `saving()` |
| 列表过滤 | `scopeQuery()` |
| 保存、删除后的关联处理 | `saved()` / `deleted()` |
| 特殊权限和数据范围 | 按需覆盖 `authorize()` / `scopeAccess()` |
| 特殊输出、写入、分页限制 | 按需设置 `readable/writable/maxPageSize`，默认不生成 |

分页示例：`filter[status]=0&sort=-id&page=1&page_size=15`。默认最多每页 100 条，保留零值与 false；核心也支持 null 筛选。
分页返回 `data`、`total`、`page_size`、`current_page`、`total_pages`。更新后离开 scope 的记录会触发回滚并返回 404。

默认规则、可选配置和修改示例见[业务 CRUD](GENERATED-CRUD.md)。

列表在数据库中分页，只加载当前页。生成器创建 `owner_id + id` 联合索引，其他筛选和关联查询应按业务设计索引。
角色、租户、库存和支付等规则应在应用服务中明确实现；通用 CRUD 不替代业务并发控制。

## 响应与异常

成功响应结构为 `{code: 0, message, data}`。创建返回 HTTP 201；删除返回 HTTP 200，`data` 为 `[]`。
空数组、false、空字符串和 null 保持各自语义。

业务错误码与 HTTP 状态独立：

```php
throw new \Ninex\Lib\Core\ServiceException(
    '库存不足',
    10001,
    ['available' => 0],
    httpStatus: 409,
);
```

旧名称 `Ninex\Lib\Exceptions\ServiceException` 继续可用。
Laravel 通过 Provider 注册 API 异常渲染，保留宿主 Handler 与 HTML 请求处理。
旧前端可临时启用 Laravel 的 `ninexlib.exceptions.legacy_http_200` 或 ThinkPHP 的 `ninexlib.legacy_http_200`。

## 环境诊断

```bash
php artisan ninexlib:doctor --strict
php think ninexlib:doctor --strict
vendor/bin/ninex doctor --strict
```

选择与宿主对应的入口执行。`doctor` 只读检查 PHP、依赖、目录和旧 Laravel 直接继承类的常见迁移项，不改写应用代码。
有错误时返回 1；开启 `--strict` 后，有警告返回 2。独立入口支持 `--path` 和 `--vendor-dir`。
静态提示不能替代应用自己的权限、HTTP 与事务测试。
