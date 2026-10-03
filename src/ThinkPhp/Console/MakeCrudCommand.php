<?php

namespace Ninex\Lib\ThinkPhp\Console;

use Ninex\Lib\Scaffolding\ConsoleApplication;
use think\console\{Command, Input, Output};
use think\console\input\{Argument, Option};

class MakeCrudCommand extends Command
{
    protected function configure()
    {
        $this->setName('ninexlib:make-crud')->setDescription('Generate an owner-scoped CRUD resource')
            ->addArgument('name', Argument::REQUIRED, 'Resource class name');
        foreach (['fields', 'table', 'route'] as $option) {
            $this->addOption($option, null, Option::VALUE_REQUIRED, $option);
        }
        $this->addOption('dry-run', null, Option::VALUE_NONE, 'Preview without changing files');
    }
    protected function execute(Input $input, Output $output)
    {
        $args = ['make:crud', $input->getArgument('name'), '--framework=thinkphp', '--namespace='.$this->app->getNamespace(), '--path='.$this->app->getRootPath()];
        foreach (['fields', 'table', 'route'] as $option) {
            if ($input->getOption($option) !== null) {
                $args[] = '--'.$option.'='.$input->getOption($option);
            }
        }
        if ($input->getOption('dry-run')) {
            $args[] = '--dry-run';
        }
        $status = (new ConsoleApplication())->run($args, fn ($line) => $output->writeln($line));
        if ($status === 0 && !$input->getOption('dry-run') && is_file($this->app->getRuntimePath().'route.php')) {
            if (!@unlink($this->app->getRuntimePath().'route.php')) {
                $output->error('Generated files exist, but the route cache could not be cleared.');
                return 1;
            }
            $output->writeln('Route cache cleared.');
        }
        return $status;
    }
}
