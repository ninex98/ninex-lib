<?php

namespace Ninex\Lib\Console;

use Illuminate\Console\Command;
use Ninex\Lib\Scaffolding\ConsoleApplication;

class DoctorCommand extends Command
{
    protected $signature = 'ninexlib:doctor {--strict : Return failure when migration warnings are found}';
    protected $description = 'Read-only environment and legacy migration diagnostics';
    public function handle(): int
    {
        $args = ['doctor', '--framework=laravel', '--path='.$this->laravel->basePath(), '--vendor-dir='.dirname((new \ReflectionClass(\Composer\InstalledVersions::class))->getFileName(), 2)];
        if ($this->option('strict')) {
            $args[] = '--strict';
        }
        return (new ConsoleApplication())->run($args, fn ($line) => $this->line($line));
    }
}
