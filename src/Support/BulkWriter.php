<?php

namespace Ninex\Lib\Support;

use Illuminate\Database\Connection;
use InvalidArgumentException;

/** Low-level bulk operations: values are bound; identifiers are trusted application configuration. */
final class BulkWriter
{
    public static function identifier(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $name)) {
            throw new InvalidArgumentException('Invalid SQL identifier: '.$name);
        }
    }

    public static function update(Connection $connection, string $table, array $rows, string $index): int
    {
        if (!$rows) {
            return 0;
        }
        self::identifier($table);
        self::identifier($index);
        $columns = array_keys(reset($rows));
        foreach ($columns as $column) {
            self::identifier($column);
        }
        $ids = [];
        foreach ($rows as $row) {
            if (array_diff($columns, array_keys($row)) || array_diff(array_keys($row), $columns) || !isset($row[$index]) || !is_scalar($row[$index])) {
                throw new InvalidArgumentException('Bulk rows must have identical fields and a scalar key.');
            }
            $ids[] = $row[$index];
        }
        if (count(array_unique($ids, SORT_STRING)) !== count($ids)) {
            throw new InvalidArgumentException('Duplicate bulk update keys.');
        }
        $columns = array_values(array_diff($columns, [$index]));
        if (!$columns) {
            return 0;
        }
        $grammar = $connection->getQueryGrammar();
        $key = $grammar->wrap($index);
        return $connection->transaction(function () use ($connection, $table, $rows, $columns, $index, $key, $grammar) {
            $affected = 0;
            // Bound parameter counts stay small even on SQLite builds with a 999-variable limit.
            $chunkSize = max(1, intdiv(900, count($columns) * 2 + 1));
            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $sets = [];
                $bindings = [];
                foreach ($columns as $column) {
                    $cases = [];
                    foreach ($chunk as $row) {
                        $cases[] = 'WHEN ? THEN ?';
                        $bindings[] = $row[$index];
                        $bindings[] = $row[$column];
                    }
                    $field = $grammar->wrap($column);
                    $sets[] = $field.' = CASE '.$key.' '.implode(' ', $cases).' ELSE '.$field.' END';
                }
                $bindings = array_merge($bindings, array_column($chunk, $index));
                $sql = 'UPDATE '.$grammar->wrapTable($table).' SET '.implode(', ', $sets).' WHERE '.$key.' IN ('.implode(', ', array_fill(0, count($chunk), '?')).')';
                $affected += $connection->update($sql, $bindings);
            }
            return $affected;
        });
    }

    public static function replace(Connection $connection, string $table, array $rows): bool
    {
        if (!$rows) {
            return true;
        }
        if (!in_array($connection->getDriverName(), ['mysql', 'sqlite'], true)) {
            throw new \LogicException('REPLACE is only supported on MySQL and SQLite; use an explicit upsert elsewhere.');
        }
        self::identifier($table);
        $columns = array_keys(reset($rows));
        foreach ($columns as $column) {
            self::identifier($column);
        }
        $values = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns) || array_diff($columns, array_keys($row))) {
                throw new InvalidArgumentException('Bulk rows must have identical fields.');
            }
            foreach ($columns as $column) {
                $values[] = $row[$column];
            }
        }
        $grammar = $connection->getQueryGrammar();
        $placeholder = '('.implode(', ', array_fill(0, count($columns), '?')).')';
        return $connection->statement('REPLACE INTO '.$grammar->wrapTable($table).' ('.implode(', ', array_map($grammar->wrap(...), $columns)).') VALUES '.implode(', ', array_fill(0, count($rows), $placeholder)), $values);
    }
}
