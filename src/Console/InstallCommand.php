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
    public function handle(): int
    {
        $this->info('开始安装 NinexLib...');

        // 发布配置文件
        $status = $this->call('vendor:publish', [
            '--tag' => 'ninexlib-config'
        ]);

        if ($status !== self::SUCCESS) {
            $this->error('配置发布失败，请检查上面的错误。');
            return $status;
        }
        $this->info('NinexLib 安装完成！');
        $this->info('可直接使用默认配置；按应用需要调整 config/ninexlib.php。');
        return self::SUCCESS;
    }
}
