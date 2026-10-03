# 开发与兼容测试

仓库只发布一个 ninex/lib。tests/environments 下的 composer.json 仅创建测试宿主，不是子包。
以下命令用于 Git 源码检出目录；Composer 发行归档不包含 tests 和 tools。

## 默认测试

```bash
composer install
composer test
composer lint
composer install --working-dir=tests/environments/laravel
php tests/environments/laravel/vendor/bin/phpunit -c phpunit.laravel.xml
composer install --working-dir=tests/environments/thinkphp
php tests/environments/thinkphp/vendor/bin/phpunit -c phpunit.thinkphp.xml
```

根环境无需 Web 框架即可测试核心和生成器，最低 PHP 8.1。
Laravel 测试宿主按当前 PHP 选择兼容版本；也可以在测试宿主中显式约束 framework 与 testbench，再 update。
例如 PHP 8.1 使用 Laravel ^10 + Testbench ^8，PHP 8.2 使用 Laravel ^11/^12 + Testbench ^9/^10，PHP 8.3 使用 Laravel ^13 + Testbench ^11。
不使用 --ignore-platform-reqs。运行时必须符合宿主框架自己的 PHP 要求。

多 PHP 环境请使用目标解释器的绝对路径运行 Composer 和测试，无须切换 Web 服务。
确保该解释器启用测试需要的扩展；`-n` 会跳过 php.ini，仅适用于所需扩展均已编译进 PHP 的环境。
库不提交 lock，真实应用应提交自己的 composer.lock。更换 PHP 后，用目标 PHP 解析依赖并运行 check-platform-reqs。

## 本地接入

在宿主根 composer.json 合并一个 path repository 即可：

```json
{
  "repositories": [
    {"type":"path","url":"/绝对路径/ninex-lib","options":{"versions":{"ninex/lib":"2.1.0"}}}
  ]
}
```

然后 `composer require ninex/lib:^2.1`。这个本地 version alias 不代表已正式发布。
发布后移除 path repository，沿用 Packagist 的同一个包名。

## 从 Packagist 试用发布分支

需要核验本次发布分支时，可在测试项目中明确指定开发版本：

```bash
composer require 'ninex/lib:dev-release/2.1.0'
```

该版本随分支提交更新，不是固定的正式版本，也无需降低整个项目的 minimum-stability。
正式接入使用 `composer require ninex/lib:^2.1`；发布后应切回稳定版约束。

## MySQL 和队列

默认使用内存 SQLite。可启动一次性 MySQL 测试实例：

```bash
NINEX_PHP_BINARY=/opt/homebrew/opt/php@8.3/bin/php NINEX_PHP_NO_INI=1 \
  tools/with-mysql.sh /opt/homebrew/opt/mysql@8.4/bin -- tools/test-frameworks.sh
```

脚本在 /tmp/ninex-mysql.* 初始化空数据目录，只开放私有 socket，结束后关闭并清理；不更改原有数据库及 brew services。
换成 mysql@8.0 可验证另一版本。已有专用测试实例可配置 NINEX_TEST_DB_DRIVER=mysql、NINEX_TEST_MYSQL_HOST/PORT/USER/PASSWORD 或 SOCKET。
测试用户需要 CREATE/DROP DATABASE 权限；只会删除本次生成的随机 ninex_test_* 数据库。

测试 bootstrap 支持 NINEX_VENDOR_DIR 与 NINEX_THINK_VENDOR_DIR，便于使用临时目录隔离更多版本组合。
数据库队列测试执行真实 queue:work --once，检查消费、回滚、failed_jobs 和提交后异常。
生成器测试实际执行生成的迁移/SQL，并通过 HTTP 路由验证 CRUD、字段校验与切换身份后的所有权隔离。

## 安全审计与兼容范围

Laravel 10/11 的兼容测试使用已停止安全维护的上游版本，当前审计确有 Laravel 安全公告。
CI 只在这两个隔离测试组合的依赖解析步骤使用 COMPOSER_POLICY_ADVISORIES_BLOCK=0，保留公告输出及其他依赖策略检查。
这个设置没有写入发行包，也不会关闭宿主应用的审计。生产项目不应照搬此测试设置来规避升级。
核心及当前 Laravel / ThinkPHP 组合正常使用 Composer 默认的依赖策略。

CI 支持 PHP 8.1～8.4、Laravel 10～13、ThinkORM 3/4、MySQL 8.0/8.4，并验证同一个发行包在三个全新宿主中的安装。
实际验证结果以对应提交的 GitHub Actions 记录为准。未验证的组合不视为已支持。

## 文档与本地记录

`docs/` 存放可复用的接入、迁移、架构、开发和发布说明，与源码一起提交。
临时验收记录、本机路径和工作笔记保存在 `.local/`，已通过 `.gitignore` 排除。构建产物和安装验证日志保存在 `build/`。
`.gitattributes` 的 `export-ignore` 只控制源码归档，不隐藏 GitHub 仓库中的文件；不应使用它代替 Git 忽略规则。
