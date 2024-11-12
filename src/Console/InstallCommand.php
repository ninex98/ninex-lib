<?php

namespace Ninex\Lib\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * 命令名称
     */
    protected $signature = 'ninexlib:install';

    /**
     * 命令描述
     */
    protected $description = '安装 NinexLib 包';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始安装 NinexLib...');

        // 发布配置文件
        $this->call('vendor:publish', [
            '--tag' => 'ninexlib-config'
        ]);

        $this->info('NinexLib 安装完成！');
        $this->info('请检查并配置 config/ninexlib.php 文件');
    }
}
