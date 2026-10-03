<?php

namespace Ninex\Lib\ThinkPhp\Console;

use Ninex\Lib\Scaffolding\ConsoleApplication;
use think\console\{Command, Input, Output};
use think\console\input\Option;

class DoctorCommand extends Command
{
    protected function configure()
    {
        $this->setName('ninexlib:doctor')->setDescription('Read-only environment diagnostics')
            ->addOption('strict', null, Option::VALUE_NONE, 'Fail when warnings are found');
    }
    protected function execute(Input $input, Output $output)
    {
        $args = ['doctor', '--framework=thinkphp', '--path='.$this->app->getRootPath()];
        if ($input->getOption('strict')) {
            $args[] = '--strict';
        }
        return (new ConsoleApplication())->run($args, fn ($line) => $output->writeln($line));
    }
}
