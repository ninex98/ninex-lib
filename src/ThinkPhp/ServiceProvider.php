<?php

namespace Ninex\Lib\ThinkPhp;

class ServiceProvider extends \think\Service
{
    public function boot(): void
    {
        $this->commands([Console\MakeCrudCommand::class, Console\DoctorCommand::class]);
    }
}
