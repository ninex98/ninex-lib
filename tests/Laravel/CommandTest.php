<?php

namespace Ninex\Lib\Tests;

use Illuminate\Contracts\Console\Kernel;
use Ninex\Lib\Console\LibCommand;

class CommandTest extends TestCase
{
    public function testCommandLoadsAndRunsWithoutAForceOption(): void
    {
        $command = new class () extends LibCommand {
            protected $signature = 'ninex:probe';

            protected function process(): int
            {
                if (!$this->confirmToProceed()) {
                    return self::FAILURE;
                }
                $this->withProgressBar([1, 2], function ($value) {
                });
                return self::SUCCESS;
            }
        };
        $this->app->make(Kernel::class)->registerCommand($command);
        $this->artisan('ninex:probe')->assertExitCode(0);
    }
    public function testInstallerPropagatesPublishFailures(): void
    {
        $command = new class () extends \Ninex\Lib\Console\InstallCommand {
            public function call($command, array $arguments = [])
            {
                return 7;
            }
        };
        $this->app->make(Kernel::class)->registerCommand($command);
        $this->artisan('ninexlib:install')->assertExitCode(7);
    }

    public function testDoctorUsesTheActualApplicationVendorDirectory(): void
    {
        $this->artisan('ninexlib:doctor')->assertExitCode(0);
    }

}
