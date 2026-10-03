<?php

namespace Ninex\Lib\Http\Middleware;

use Closure;
use Ninex\Lib\Support\SqlRecord;

class ResetSqlRecord
{
    public function handle($request, Closure $next)
    {
        app(SqlRecord::class)->clear();
        try {
            return $next($request);
        } finally {
            app(SqlRecord::class)->clear();
        }
    }
}
