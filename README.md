# Ninex Lib

一个为 Laravel 项目提供基础功能扩展的包，包含常用的基类和工具类。

## 功能特性

- 统一的控制器基类 (LibController)
- 增强的服务层基类 (LibService)
- 扩展的模型基类 (LibModel)
- 命令行工具基类 (LibCommand)
- 队列基类 (LibJob)
- HTTP 客户端封装 (LibClient)
- 通用的辅助特征 (Traits)
- 标准化的响应格式 (ResponseTrait)
- 统一的错误处理 (LibExceptionHandler)

## 安装

```bash
composer require ninex/lib
```

## 配置

1. 发布配置文件

```bash
php artisan vendor:publish --provider="Ninex\Lib\LibServiceProvider"
```

2. 配置文件 `config/ninexlib.php`

```php
return [
    'http' => [
        'timeout' => env('NINEX_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('NINEX_HTTP_CONNECT_TIMEOUT', 10),
    ],
    'file' => [
        'disk' => env('NINEX_FILE_DISK', 'public'),
        'path' => env('NINEX_FILE_PATH', 'uploads'),
    ],
];
```

## 开发计划 (Roadmap)

### 1. 核心组件 (Core)
- [ ] 缓存管理 (LibCache)
    - 多驱动支持 (Redis, Memcached)
    - 缓存标签和自动清理

- [ ] 存储管理 (LibStorage)
    - 本地和云存储 (OSS, S3)
    - 文件处理工具

- [ ] 队列服务 (LibQueue)
    - 多驱动支持 (Redis, RabbitMQ)
    - 重试机制和死信处理

- [ ] 日志服务 (LibLogger)
    - 多通道和分级日志
    - 自定义格式化

### 2. 工具组件 (Utils)
- [ ] 文件工具 (FileUtils)
    - 文件上传下载（分片、断点续传）
    - 图片处理（压缩、裁剪、水印）
    - 文档处理（Excel、PDF、Word）

- [ ] 数据工具 (DataUtils)
    - 字符串处理（加密、脱敏）
    - 时间处理（格式化、转换）
    - 数组处理（树形、递归）

- [ ] 网络工具 (NetworkUtils)
    - API 签名验证
    - 并发请求处理
    - 地理位置服务

- [ ] 导出工具 (ExportUtils)
    - 大数据导出
    - 多格式支持
    - 异步处理