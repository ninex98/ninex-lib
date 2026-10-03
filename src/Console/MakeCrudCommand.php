<?php

namespace Ninex\Lib\Console;

use Illuminate\Console\Command;
use Ninex\Lib\Scaffolding\{CrudGenerator, FileWriter, ResourceDefinition};
use Throwable;

class MakeCrudCommand extends Command
{
    protected $signature = 'ninexlib:make-crud {name} {--fields=} {--table=} {--route=} {--guard=} {--dry-run}';
    protected $description = 'Generate an authenticated, owner-scoped CRUD resource without overwriting application files';

    public function handle(): int
    {
        try {
            $definition = new ResourceDefinition($this->argument('name'), 'laravel', $this->option('fields') ?? '', $this->option('table'), $this->option('route'), rtrim($this->laravel->getNamespace(), '\\'), $this->option('guard'));
            $files = (new CrudGenerator())->plan($definition, $this->laravel->basePath());
            if (!$this->option('dry-run')) {
                (new FileWriter())->write($this->laravel->basePath(), $files);
                if (is_file($this->laravel->getCachedRoutesPath())) {
                    $status = $this->call('route:clear');
                    $this->laravel->forgetInstance('routes.cached');
                    if ($status !== self::SUCCESS || is_file($this->laravel->getCachedRoutesPath())) {
                        $this->error('Generated files exist, but route cache clearing failed.');
                        return self::FAILURE;
                    }
                }
            }
            foreach (array_keys($files) as $file) {
                $this->line(($this->option('dry-run') ? '[preview] ' : '[created] ').$file);
            }
            if ($this->option('dry-run')) {
                return self::SUCCESS;
            }
            $this->info('Run php artisan migrate, then access the resource under the configured API prefix. Configure the authentication guard for your application.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
