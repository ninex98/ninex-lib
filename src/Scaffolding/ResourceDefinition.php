<?php

namespace Ninex\Lib\Scaffolding;

use InvalidArgumentException;

/** One validated definition feeds both framework renderers. No application or ORM is booted. */
final class ResourceDefinition
{
    public readonly array $fields;
    public readonly string $table;
    public readonly string $route;
    public readonly string $namespace;

    public function __construct(
        public readonly string $name,
        public readonly string $framework,
        string $fields,
        ?string $table = null,
        ?string $route = null,
        ?string $namespace = null,
        public readonly ?string $guard = null,
    ) {
        if (!in_array($framework, ['laravel', 'thinkphp'], true)) {
            throw new InvalidArgumentException('Framework must be laravel or thinkphp.');
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/D', $name)) {
            throw new InvalidArgumentException('Resource name must be a simple PascalCase class name.');
        }
        if (!function_exists('token_get_all')) {
            throw new InvalidArgumentException('The CRUD generator requires the PHP tokenizer extension.');
        }
        try {
            token_get_all('<?php class '.$name.' {}', TOKEN_PARSE);
        } catch (\ParseError $e) {
            throw new InvalidArgumentException('Resource name is a reserved PHP keyword.', 0, $e);
        }
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $plural = preg_match('/[^aeiou]y$/', $snake) ? substr($snake, 0, -1).'ies' : $snake.(preg_match('/(s|x|z|ch|sh)$/', $snake) ? 'es' : 's');
        $this->table = $table ?? $plural;
        $this->route = $route ?? $this->table;
        if (!preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $this->table) || !preg_match('/^[a-z][a-z0-9_-]{0,47}$/D', $this->route)) {
            throw new InvalidArgumentException('Table/route names must be lowercase identifiers of at most 48 characters.');
        }
        $this->namespace = trim($namespace ?? ($framework === 'laravel' ? 'App' : 'app'), '\\');
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $this->namespace)) {
            throw new InvalidArgumentException('Invalid application namespace.');
        }
        if ($guard !== null && ($framework !== 'laravel' || !preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/D', $guard))) {
            throw new InvalidArgumentException('--guard accepts a Laravel guard name only.');
        }
        $parsed = [];
        foreach (explode(',', $fields) as $field) {
            if (!preg_match('/^([a-z][a-z0-9_]{0,47}):(string|text|integer|boolean|date|datetime)(\?)?$/D', trim($field), $match)) {
                throw new InvalidArgumentException('Fields use name:type, e.g. name:string,status:boolean,note:text?.');
            }
            if (isset($parsed[$match[1]]) || in_array($match[1], ['id', 'owner_id', 'created_at', 'updated_at'], true)) {
                throw new InvalidArgumentException('Duplicate or reserved field: '.$match[1]);
            }
            $parsed[$match[1]] = ['type' => $match[2], 'nullable' => isset($match[3])];
        }
        if (count($parsed) > 32) {
            throw new InvalidArgumentException('At most 32 fields can be generated at once.');
        }
        $this->fields = $parsed;
    }
}
