<?php

namespace Ninex\Lib\Scaffolding;

use InvalidArgumentException;
use Throwable;

final class ConsoleApplication
{
    public function run(array $arguments, callable $output): int
    {
        try {
            if (!$arguments || in_array('--help', $arguments, true) || in_array('-h', $arguments, true) || $arguments[0] === 'help') {
                $output('Usage: vendor/bin/ninex make:crud Product --fields="name:string,status:boolean" [--framework=laravel|thinkphp] [--table=products] [--route=products] [--namespace=App] [--guard=sanctum] [--path=.] [--dry-run]');
                $output('       vendor/bin/ninex doctor [--framework=core|laravel|thinkphp] [--path=.] [--strict]');
                return 0;
            }
            $command = array_shift($arguments);
            if (!in_array($command, ['make:crud', 'doctor'], true)) {
                throw new InvalidArgumentException('Unknown command. Use --help.');
            }
            $name = $command === 'make:crud' ? (array_shift($arguments) ?? '') : '';
            $options = $this->options($arguments, $command);
            $project = realpath($options['path'] ?? getcwd());
            if ($project === false || !is_dir($project)) {
                throw new InvalidArgumentException('Project directory does not exist.');
            }
            $doctor = new ProjectDoctor();
            $framework = $options['framework'] ?? $doctor->detectFramework($project, $options['vendor-dir'] ?? null) ?? ($command === 'doctor' ? 'core' : null);
            if ($command === 'doctor') {
                $checks = $doctor->inspect($project, $framework, $options['vendor-dir'] ?? null);
                foreach ($checks as $check) {
                    $output('['.strtoupper($check['level']).'] '.$check['message']);
                }
                $output('Read-only checks completed. Static migration hints do not replace application HTTP/authorization tests.');
                $levels = array_column($checks, 'level');
                return in_array('error', $levels, true) ? 1 : (isset($options['strict']) && in_array('warning', $levels, true) ? 2 : 0);
            }
            if ($framework === null || !isset($options['fields'])) {
                throw new InvalidArgumentException('--fields is required; specify --framework when it cannot be detected from the project.');
            }
            $definition = new ResourceDefinition($name, $framework, $options['fields'], $options['table'] ?? null, $options['route'] ?? null, $options['namespace'] ?? null, $options['guard'] ?? null);
            $files = (new CrudGenerator())->plan($definition, $project);
            if (!isset($options['dry-run'])) {
                (new FileWriter())->write($project, $files);
            }
            foreach (array_keys($files) as $path) {
                $output((isset($options['dry-run']) ? '[preview] ' : '[created] ').$path);
            }
            if (isset($options['dry-run'])) {
                $output('Preview only; no files were changed.');
                return 0;
            }
            $output($definition->framework === 'laravel'
                ? 'Next: php artisan migrate; php artisan route:clear if routes were cached. Configure the authentication guard.'
                : 'Next: apply the generated SQL for your database and provide a trusted actor[id] in authentication middleware. Generated routes include JSON exception handling.');
            $output('Default access: authenticated users manage their own records. owner_id expects an integer user ID.');
            return 0;
        } catch (Throwable $e) {
            $output('Error: '.$e->getMessage());
            return 1;
        }
    }

    private function options(array $arguments, string $command): array
    {
        $values = $command === 'doctor' ? ['framework', 'path', 'vendor-dir'] : ['framework', 'fields', 'table', 'route', 'namespace', 'guard', 'path'];
        $flag = $command === 'doctor' ? 'strict' : 'dry-run';
        $options = [];
        while ($arguments) {
            $argument = array_shift($arguments);
            if ($argument === '--'.$flag && !isset($options[$flag])) {
                $options[$flag] = true;
                continue;
            }
            if (!preg_match('/^--([a-z-]+)(?:=(.*))?$/sD', $argument, $match) || !in_array($match[1], $values, true) || isset($options[$match[1]])) {
                throw new InvalidArgumentException('Invalid or duplicate option: '.$argument);
            }
            $value = $match[2] ?? array_shift($arguments);
            if (!is_string($value) || $value === '' || str_starts_with($value, '--')) {
                throw new InvalidArgumentException('Missing value for --'.$match[1]);
            }
            $options[$match[1]] = $value;
        }
        return $options;
    }
}
