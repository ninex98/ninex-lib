# 从 1.x 升级到 2.x

2.x 是主版本升级，保留旧 Laravel 基类的字段、验证和权限接入方式，同时引入独立核心与代码生成。响应、筛选等变化仍需在应用测试环境确认。
保留 `ninex/lib` 包名、主要 Laravel 类名及 CRUD 方法名；核心与 ThinkPHP 接入均已内置，不再有需要独立发布的子包。

| 项目 | 2.x 行为 | 应用迁移 |
|---|---|---|
| 版本 | PHP ^8.1；Laravel 10～13 | 宿主版本仍决定实际 PHP 下限；Laravel 10 可用 PHP 8.1，13 需要 >=8.3 |
| LibModel 写入 | 保留 guarded=['id']，不强制新增 fillable | 现有模型可继续使用；请求字段在业务层筛选、验证；已有 fillable 继续生效 |
| LibService 验证 | validateForm 可选，默认空实现；validateStore/validateUpdate 显式调用一次 | create/store/update 只持久化，不重复验证已转换的数据；原有验证调用方式可保留 |
| 旧查询筛选 | 默认 allowedFilters=[]，保留旧平面参数格式 | 为旧 Service 声明 allowedFilters；核心使用 filter[field] 格式 |
| 分页 | 页数/页大小必须正整数，限制最大页大小 | 清理不合法参数；核心不接受任意附加查询参数 |
| 控制器授权 | 旧控制器沿用应用权限；protected bool $usePolicy = true 可启用 Gate/Policy | 默认无需补 Policy；显式启用后缺少规则会拒绝；新核心仍执行必需的授权回调 |
| 控制器生命周期 | serviceClass 由 callAction 在认证后、业务方法前重新解析 | 推荐 serviceClass；不要跨请求缓存身份相关 Service；覆写 callAction 时调用父方法，action 内无需重复 prepareCrud；继承的旧 CRUD 支持直接调用；自定义 action 应经路由分发 |
| 写事务 | 旧 CRUD 服务和核心服务负责，控制器默认不额外开事务 | 自定义多表业务在服务层明确定义事务边界 |
| HTTP 状态 | 创建 201，错误真实状态；删除仍为 200 + data:[] | 更新前端拦截器；旧协议可临时启用 exceptions.legacy_http_200 |
| 业务错误 | ServiceException 增加 previous 和独立 httpStatus | 不再把自定义业务码直接当 HTTP 状态 |
| 响应 | []/false/空字符串不再变成 null；Resource 使用 resolve | 更新依赖旧空值行为的客户端断言 |
| afterTransaction | 等待最外层提交；回调失败只报告，不重试已提交写入 | 关键业务放事务内；可靠消息用 outbox |
| 事务钩子 | 一次性消费，不跨下一次调用残留 | 每次 transaction 前重新注册需要的钩子 |
| withTableLock | 抛 LogicException，禁止事务中隐式提交 | 改为显式 Builder + withRowLock 或专用并发方案 |
| batchUpdate | 参数绑定、标识符校验、统一列集；LibModel 空输入仍返回 true，事务 Trait 仍返回 0 | 不能用它绕过权限或模型事件 |
| replace | 限 MySQL/SQLite，使用绑定；REPLACE 可能删除后重插 | 常规更新优先使用框架 upsert，确认业务语义 |
| 缓存 TTL | cacheMinutes 和 remember(...minutes) 真正按分钟转换 | 原来以秒传入却命名 minutes 的调用需改值 |
| authGuard | 旧 LibService 保留 api；新生成 Service 默认使用宿主 guard | 新生成 API 可用 --guard=sanctum 等选择宿主已有配置 |
| 模型缓存 | 默认关闭；事务内绕过缓存；模型事件提交后失效 | 需要时显式启用 model_cache.enabled |
| simplePaginate | 保留 1.x 的总数分页行为；新增 paginateWithoutTotal 才跳过 COUNT | 旧代码不用改；切换无总数分页时读取 has_more |
| SQL 调试 | 默认关闭、绑定值不采集、请求范围、限量 | 旧 SqlRecord::$sql 改为 app(SqlRecord::class)->all() |
| HTTP 客户端 | TLS verify=true；204=[]；无效 JSON 为业务异常 502 | 修复测试环境 CA；处理明确的上游协议错误 |

缓存特别说明：缓存键包含类、连接、数据库、表、ID 和 cacheContext。
多租户/按用户变化的全局 scope 必须覆盖 `protected function cacheContext(): string`，从服务器身份生成上下文。
缓存键无法自行推断权限规则。模型事件之外的更新不会自动失效（包括 query-builder update/delete、外部应用写入）。
启用模型缓存时 LibModel::batchUpdate 明确拒绝执行；用逐模型更新或应用自行管理缓存。
若模型覆盖 boot，需要调用 parent::boot()。缓存失效在最外层提交后执行；回滚不失效。
缓存读取失败回源数据库；提交后失效失败只报告，不能把已提交写入当作失败重试。这仍属于最终一致性缓存，强一致性读取请保持默认关闭。

兼容回归覆盖旧模型无返回类型的 boot、旧 ServiceException 的 data/getData、HTTP Client 的 Client 属性、ResponseTrait::error 签名，以及 LibExceptionHandler 的 protected 扩展钩子。

旧服务已有“validateForm → 转换字段 → create/update”的流程可以继续保留。输入结构和数据库结构不必相同，底层保存方法不会再次调用表单验证。
新生成的 CRUD 在 Service 中声明 writable 与验证规则，不要求再给模型手写一份 fillable；认证、所有权范围和授权回调仍然生效。

异常 Handler 通过 Provider 的 renderable 回调接入，不替换宿主 Handler。原 LibExceptionHandler 仍可继承。
已有自定义异常渲染、JSON 协议、认证中间件的应用，要用其真实 HTTP 测试确认组合顺序。
ThinkPHP 生成控制器内已处理 API 异常；其他 API 可通过 app/provider.php 显式绑定。`ninexlib.legacy_http_200` 是对应的旧协议开关，也应用于创建成功响应。

FilterInterface 仍是 Eloquent 专用接口；TransactionAware 为兼容保留，不能当作已注册的生命周期机制。
旧业务使用明确的 saving/saved/deleted 或事务回调；原回调式核心 Service 保持可用；2.1 新模板采用显式框架 Service，参见[业务模板迁移](GENERATED-CRUD.md#事务与兼容)。

新增生成器：php artisan ninexlib:make-crud、php think ninexlib:make-crud 或 vendor/bin/ninex make:crud。
先运行 php artisan ninexlib:doctor --strict 检查常见迁移项；静态提示不会自动改写业务字段、权限或数据。
生成的 Laravel 路由位于 routes/ninex，默认自动挂载到 api 前缀，可通过 ninexlib.routes 配置关闭或调整。
Guzzle 不再作为所有用户的强制依赖；Laravel 10 等未自带它的项目若使用 LibClient，应显式安装 guzzlehttp/guzzle:^7.0。

两个仓储统一在更新后重新检查 scope；记录离开可见范围时返回 404 并回滚。空 readable 输出不再影响记录存在性判断。

升级验收至少覆盖：未登录、无权限、越租户访问、非法字段、零值筛选、分页上限、回滚、空值响应和旧前端错误处理。
