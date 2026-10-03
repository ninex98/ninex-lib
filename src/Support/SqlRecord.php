<?php

namespace Ninex\Lib\Support;

use Illuminate\Support\Facades\DB;

/** Request/job scoped collector. Bindings are deliberately excluded. */
class SqlRecord
{
    private array $queries = [];

    public static function listen(): void
    {
        DB::listen(function ($query) {
            if (config('app.debug') && config('ninexlib.sql.enabled', false)) {
                app(self::class)->record($query->sql, $query->time);
            }
        });
    }

    public function record(string $sql, float $time): void
    {
        $limit = max(0, (int) config('ninexlib.sql.limit', 100));
        if (count($this->queries) < $limit) {
            $this->queries[] = ['sql' => $sql, 'time_ms' => $time];
        }
    }

    public function all(): array
    {
        return $this->queries;
    }
    public function clear(): void
    {
        $this->queries = [];
    }
}
