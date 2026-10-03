# 架构与范围

唯一发布单元是 ninex/lib。src/Core、现有 Laravel 目录和 src/ThinkPhp 是内部模块，不是需要独立发布的包。
composer.json 的运行时 require 只有 PHP ^8.1。框架及 Guzzle 是可选宿主依赖，Composer 不会为了使用核心安装它们。
Laravel Provider 的自动接入限制在已验证的 10～13 主版本，其他宿主仍可使用公共核心。

核心使用 ServiceException、Result、Page、Query、CrudRepository、CrudService。
核心不访问全局容器、请求或配置，不返回 ORM Builder/Model。验证和权限使用显式闭包，仓储负责原生持久化和连接事务。
Eloquent 仓储执行模型保存/删除事件；ThinkORM 仓储使用查询构造器。复杂查询、软删除、模型事件及领域操作留在适配/业务实现中。

旧 Laravel 基类保持 1.x 的职责：LibModel 默认只保护 id；GeneralHelpers 的 create/store/update 接收可直接持久化的数据；validateStore/validateUpdate 才调用可选的 validateForm。
验证请求和保存已转换数据是两个阶段，不通过实例状态推断某份数据是否已经验证。旧控制器默认不增加 Gate 检查，通过 usePolicy 显式接入；关闭时更新/删除也不为授权额外查找一次记录。
新核心和生成器继续使用必需的验证/授权回调与字段白名单，生成字段定义自动填写相关配置。

Scaffolding 的 ResourceDefinition 统一校验字段和选项，CrudGenerator 根据框架选择模板，FileWriter 在完整预检查后独占创建文件。
Artisan、ThinkPHP Console 和独立 bin/ninex 复用同一套生成逻辑。生成失败清理本次已写文件，永远不覆盖原应用文件。
默认生成认证后的所有权 CRUD：scope、写入白名单、输入验证、授权和输出白名单分别承担职责。
ThinkPHP 控制器延迟创建服务并在 action 内捕获错误，不要求替换宿主全局 Handler；其他 API 的全局处理按需接入。
生成的代码属于宿主应用，可直接编辑；运行时不解析模板，不需要额外框架检测或代理层。

列表按数据库 LIMIT 分页，Laravel 有最多两次查询/只实例化当前页模型的回归约束。生成的 owner_id/id 联合索引覆盖默认所有权及 ID 排序场景。
这不保证任意字段组合都高效；大表深分页、全文搜索、关联聚合需要针对业务查询设计索引或专用仓储。

写操作使用仓储自身连接的事务，不提供跨连接分布式事务。更新后重新检查 scope，离开范围则抛错，由 Service 回滚。
余额/库存等业务仍需行锁、乐观锁或幂等策略。普通 CRUD 不替代领域并发控制。

旧事务 Trait 的 afterTransaction 是最外层提交后的非关键回调。失败报告给宿主异常处理器，不让已提交写入被队列重试。
可靠消息投递应使用 outbox。saving/saved/deleted 位于旧 CRUD 服务事务内，抛错会回滚。
身份相关 Service 不注册为跨请求 singleton。SQL 采集默认关闭、限量、不含绑定值，并在请求边界清理。

测试按环境隔离：根目录只安装 PHPUnit/Pint；tests/environments/laravel 和 thinkphp 是测试宿主配置，既不发布，也不会成为生产依赖。
