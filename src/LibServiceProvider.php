<?php

namespace Ninex\Lib;

use Illuminate\Support\ServiceProvider;
use Ninex\Lib\Console\InstallCommand;
use Ninex\Lib\Support\SqlRecord;

class LibServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 合并配置文件
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ninexlib.php', 'ninexlib'
        );
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/ninexlib.php' => config_path('ninexlib.php'),
        ], 'ninexlib-config');

        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }

        // 记录 sql
        if (config('app.debug')) {
            SqlRecord::listen();
        }
    }
}
