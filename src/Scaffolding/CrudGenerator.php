<?php

namespace Ninex\Lib\Scaffolding;

final class CrudGenerator
{
    /** @return array<string, string> Relative path => content, usable for previews and filesystem tests. */
    public function plan(ResourceDefinition $definition, string $project): array
    {
        $d = $definition;
        $fieldNames = array_keys($d->fields);
        $readable = array_merge(['id'], $fieldNames);
        $filters = array_keys(array_filter($d->fields, fn ($field) => $field['type'] !== 'text'));
        $export = static fn (array $values) => '['.implode(', ', array_map(fn ($value) => var_export($value, true), $values)).']';
        $variables = [
            '{{namespace}}' => $d->namespace, '{{name}}' => $d->name, '{{table}}' => $d->table, '{{route}}' => $d->route,
            '{{writable}}' => $export($fieldNames), '{{readable}}' => $export($readable), '{{filters}}' => $export($filters),
            '{{auth}}' => $d->guard === null ? 'auth' : 'auth:'.$d->guard,
            '{{guard}}' => var_export($d->guard, true),
        ];
        if ($d->framework === 'laravel') {
            $rules = [];
            $columns = [];
            foreach ($d->fields as $name => $field) {
                $rule = $this->rule($field['type'], 'laravel');
                $presence = $field['nullable'] ? "'nullable'" : "(\$operation === 'store' ? 'required' : 'sometimes|required')";
                $rules[] = "                '{$name}' => {$presence}.'|{$rule}',";
                $method = match ($field['type']) {
                    'text' => 'longText', 'datetime' => 'dateTime', default => $field['type']
                };
                $columns[] = "            \$table->{$method}('{$name}')".($field['nullable'] ? '->nullable()' : '').';';
            }
            $variables['{{rules}}'] = implode("\n", $rules);
            $variables['{{columns}}'] = implode("\n", $columns);
            $existing = glob(rtrim($project, '/').'/database/migrations/*_create_'.$d->table.'_table.php') ?: [];
            $migration = $existing ? basename($existing[0]) : gmdate('Y_m_d_His').'_create_'.$d->table.'_table.php';
            $map = [
                'model.stub' => 'app/Models/'.$d->name.'.php',
                'service.stub' => 'app/Services/'.$d->name.'Service.php',
                'controller.stub' => 'app/Http/Controllers/'.$d->name.'Controller.php',
                'migration.stub' => 'database/migrations/'.$migration,
                'routes.stub' => 'routes/ninex/'.$d->route.'.php',
            ];
        } else {
            $rules = [];
            foreach ($d->fields as $name => $field) {
                $rule = $this->rule($field['type'], 'thinkphp');
                if ($field['nullable']) {
                    $rules[] = "                if (isset(\$data['{$name}'])) { \$rules['{$name}'] = '{$rule}'; }";
                } else {
                    $rules[] = "                if (\$operation === 'store' || array_key_exists('{$name}', \$data)) { \$rules['{$name}'] = 'require|{$rule}'; }";
                }
            }
            $variables['{{rules}}'] = implode("\n", $rules);
            $variables['{{mysql_schema}}'] = $this->sql($d, 'mysql');
            $variables['{{sqlite_schema}}'] = $this->sql($d, 'sqlite');
            $map = [
                'service.stub' => 'app/service/'.$d->name.'Service.php',
                'controller.stub' => 'app/controller/'.$d->name.'Controller.php',
                'routes.stub' => 'route/ninex_'.$d->route.'.php',
                'mysql.stub' => 'database/ninex/'.$d->table.'.mysql.sql',
                'sqlite.stub' => 'database/ninex/'.$d->table.'.sqlite.sql',
            ];
        }
        $result = [];
        foreach ($map as $stub => $path) {
            $template = file_get_contents(dirname(__DIR__, 2).'/resources/stubs/'.$d->framework.'/'.$stub);
            $result[$path] = strtr($template, $variables);
        }
        return $result;
    }

    private function rule(string $type, string $framework): string
    {
        return match ($type) {
            'string' => 'string|max:255', 'text' => 'string|max:65535',
            'integer' => 'integer|between:-2147483648,2147483647',
            'boolean' => $framework === 'laravel' ? 'boolean' : 'boolean|in:0,1',
            'date' => ($framework === 'laravel' ? 'date_format:' : 'dateFormat:').'Y-m-d',
            'datetime' => ($framework === 'laravel' ? 'date_format:' : 'dateFormat:').'Y-m-d H:i:s',
        };
    }

    private function sql(ResourceDefinition $d, string $driver): string
    {
        $columns = [$driver === 'mysql' ? '    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : '    id INTEGER PRIMARY KEY AUTOINCREMENT'];
        $columns[] = '    owner_id BIGINT NOT NULL';
        foreach ($d->fields as $name => $field) {
            $type = match ($field['type']) {
                'string' => 'VARCHAR(255)', 'text' => $driver === 'mysql' ? 'LONGTEXT' : 'TEXT',
                'integer' => 'INTEGER', 'boolean' => 'BOOLEAN', 'date' => 'DATE', 'datetime' => 'DATETIME',
            };
            $columns[] = '    `'.$name.'` '.$type.($field['nullable'] ? ' NULL' : ' NOT NULL');
        }
        return 'CREATE TABLE `'.$d->table.'` ('."\n".implode(",\n", $columns)."\n)".($driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '').";\n".
            'CREATE INDEX '.$d->table.'_owner_id_idx ON `'.$d->table.'` (owner_id, id);';
    }
}
