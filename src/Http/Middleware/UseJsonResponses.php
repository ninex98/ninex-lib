<?php

namespace Ninex\Lib\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Generated routes are JSON APIs, including authentication and validation failures. */
class UseJsonResponses
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
