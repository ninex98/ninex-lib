# 架构与范围

唯一发布单元是 ninex/lib。src/Core、现有 Laravel 目录和 src/ThinkPhp 是内部模块，不是需要独立发布的包。
composer.json 的运行时 require 只有 PHP ^8.1。框架及 Guzzle 是可选宿主依赖，Composer 不会为了使用核心安装它们。
Laravel Provider 的自动接入限制在已验证的 10～13 主版本，其他宿主仍可使用公共核心。

核心使用 ServiceException、Result、Page、Query、CrudRepository、CrudService。
核心不访问全局容器、请求或配置，不返回 ORM Builder/Model。验证和权限使用显式闭包，仓储负责原生持久化和连接事务。
Eloquent 仓储执行模型保存/删除事件；ThinkORM 仓储使用查询构造器。复杂查询、软删除、模型事件及领域操作留在适配/业务实现中。

旧 Laravel 基类保持 1.x 的职责：LibModel 默认只保护 id；GeneralHelpers 的 create/store/update 接收可直接持久化的数据；validateStore/validateUpdate 才调用可选的 validateForm。
验证请求和保存已转换数据是两个阶段，不通过实例状态推断某份数据是否已经验证。旧控制器默认不增加 Gate 检查，通过 usePolicy 显式接入；关闭时更新/删除也不为授权额外查找一次记录。
公共核心保留验证/授权回调接口。新的框架业务 Service 显式保留简短 CRUD 入口、验证和过滤方法；字段默认值从验证规则与数据库字段推导，可选名单由业务按需设置。

Scaffolding 的 ResourceDefinition 统一校验字段和选项，CrudGenerator 根据框架选择模板，FileWriter 在完整预检查后独占创建文件。
Artisan、ThinkPHP Console 和独立 bin/ninex 复用同一套生成逻辑。生成失败清理本次已写文件，永远不覆盖原应用文件。
默认生成认证后的所有权 CRUD：scope、写入白名单、输入验证、授权和输出白名单分别承担职责。
ThinkPHP 控制器延迟创建服务并在 action 内捕获错误，不要求替换宿主全局 Handler；其他 API 的全局处理按需接入。
生成的代码属于宿主应用，可直接编辑；运行时不解析模板，不需要额外框架检测或代理层。

显式模板使用 Laravel EloquentService 与 ThinkPHP TableService，不强制继承 Core\CrudService。两者只共享 CrudActions 对外调用契约和字段/分页解析，原生查询类型各自保留。公共 CRUD 流程、授权和钩子调度放在基类；业务文件只保留简短实例方法调用、验证和过滤入口，以及 saving/saved/deleted。字段转换统一放在 saving，不再生成 prepareSaveData。底层持久化方法不重复验证或调用钩子。Laravel 生成模型继承 LibModel，内部钩子使用 Model；ThinkPHP 内部钩子使用完整数组记录。响应字段默认由规则与表字段推导，readable 是可选覆盖。
Laravel 控制器通过 callAction 在中间件之后解析服务；ThinkPHP 新 ServiceController 与旧 CrudController 共用响应处理 Trait，同时保留旧控制器的服务类型签名。

列表按数据库 LIMIT 分页；旧核心仓储的 Laravel 列表有最多两次查询/只实例化当前页模型的回归约束。新框架 Service 还会在每个实例中读取一次字段元数据以推导默认查询和输出字段。生成的 owner_id/id 联合索引覆盖默认所有权及 ID 排序场景。
这不保证任意字段组合都高效；大表深分页、全文搜索、关联聚合需要针对业务查询设计索引或专用仓储。

公共核心写操作使用仓储连接事务；显式框架 Service 使用模型或配置的数据库连接事务，不提供跨连接分布式事务。持久化后以及 saved 钩子后均重新检查数据范围，离开范围则抛错并回滚。业务保存、删除钩子与关联写入必须使用同一连接。数据范围和列表过滤分别分组，避免自定义 OR 过滤越过范围。
余额/库存等业务仍需行锁、乐观锁或幂等策略。普通 CRUD 不替代领域并发控制。

旧事务 Trait 的 afterTransaction 是最外层提交后的非关键回调。失败报告给宿主异常处理器，不让已提交写入被队列重试。
可靠消息投递应使用 outbox。saving/saved/deleted 位于旧 CRUD 服务事务内，抛错会回滚。
身份相关 Service 不注册为跨请求 singleton。SQL 采集默认关闭、限量、不含绑定值，并在请求边界清理。

测试按环境隔离：根目录只安装 PHPUnit/Pint；tests/environments/laravel 和 thinkphp 是测试宿主配置，既不发布，也不会成为生产依赖。
