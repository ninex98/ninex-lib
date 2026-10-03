# 一个包、一套标签的发布流程

唯一包名是 ninex/lib，继续使用 github.com/ninex98/ninex-lib 与已有 Packagist 项目。
没有独立 core/ThinkPHP 包、分仓库发布或版本联动步骤。

## 发布验收

本次直接发布 `v2.0.0` 正式版，不要求先发布 RC。
本库的自动化兼容测试不能替代应用自身的字段、权限、前端协议和事务验收。
正式发布后使用 `composer require ninex/lib:^2.0`。
必要改动见[迁移指南](UPGRADE-2.md)。记录应用验收结果，并确认目标提交的 GitHub Actions 全部通过。

## 正常发布

1. 确认 CHANGELOG.md 的版本和实际发布日期、README 的安装命令与目标版本一致。
2. 提交并推送功能分支，确认该提交的 GitHub Actions 全部通过。将验收代码合并到 main，使仓库首页文档与发布内容一致，并以 main 目标提交的 CI 结果作为打标签依据。
3. 在 main 的已验收提交上创建正式版标签并推送：

```bash
git tag -a v2.0.0 -m "Ninex Lib 2.0.0"
git push origin v2.0.0
```

不覆盖或强推已有标签。兼容的问题修复使用 `v2.0.1`、`v2.0.2`；兼容的新功能使用 `v2.1.0`；不兼容修改需要新的主版本。

4. 标签推送触发两条流程：

- Packagist：已有 GitHub webhook 自动抓取标签。它直接跟随标签，不等待 GitHub Actions，所以打标签之前先确保分支测试通过。
- GitHub Release：release.yml 复用完整测试工作流，通过后创建对应 Release，并生成变更记录。alpha/beta/rc 标签自动标为预发布。

Release 使用 GitHub 自动提供的源码归档，不需要手工上传 ZIP，也不需要新的 Packagist Token。
工作流使用内置 GITHUB_TOKEN，只有创建 Release 的 job 请求 contents:write；仓库需要启用 Actions 并允许该权限。
已存在的 Release 不重复创建，也不覆盖手工编辑过的正文或附件；失败后可以重新运行该工作流。
另一个 job 通过公开 API 检查 Packagist 版本，最多等待数分钟。同步失败时检查已有 webhook 或在 Packagist 页面点击 Update。

提交时一并包含 `docs/`（含 README 封面）、`resources/`、`bin/`、`tests/`、`tools/` 与 `.github/`。
`vendor/`、`build/`、`.local/` 继续忽略；不要把内部验收记录、测试副本或本机依赖提交到仓库。
测试和工作流需要保留在 Git 中，`.gitattributes` 会将它们排除出用户下载的发行归档。

可以本地检查标签格式和已存在的 Packagist 版本：

```bash
php tools/release-metadata.php v2.0.0
php tools/check-packagist.php --tag=1.0.9 --attempts=1
```

## 可选的发行包安装验证

这只是开发验证，正常发布无需操作这些 ZIP：

```bash
php tools/build-package.php
php tools/test-consumers.php --composer="$(command -v composer)"
```

默认生成一个 build/packages/ninex-lib-2.0.0.zip，另有 SHA256SUMS 和本地 Composer 仓库索引。
安装验证默认使用 Composer 的 stable 策略；验证其他版本时，构建和安装命令都传入相同的 `--version`。
标签触发的 CI 自动使用标签对应版本，例如 v2.0.1，无需为每次修复版本改写构建脚本。
压缩包包含运行时源码、模板、CLI、文档示例；不含 vendor、测试、开发脚本或多包路径。
本地默认打包当前源码，相同内容使用排序和固定时间戳。CI 使用 `php tools/build-package.php --ref=HEAD` 按 Git 的 export-ignore 规则生成归档，验证实际标签的分发内容。

三个全新宿主安装的是同一个 ninex/lib：核心宿主不安装框架，Laravel / ThinkPHP 宿主声明各自框架依赖。
Composer manifest 与源码一致；作为依赖安装时 Composer 不安装本库 require-dev，也不执行本库 scripts。
验证包括 --no-dev ZIP 安装、平台检查、CLI 代理、Laravel 自动发现、生成的迁移/路由/CRUD，以及 ThinkPHP 服务发现、原生命令和生成接口。
目录保留在 build/consumers-*，日志和 build/consumer-report.json 记录版本及结果。
生产使用 GitHub 标签/Packagist；本地 file:// 索引及 version alias 不表示远程已经发布。
