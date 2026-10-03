<?php

namespace Ninex\Lib;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Ninex\Lib\Console\InstallCommand;
use Ninex\Lib\Console\MakeCrudCommand;
use Illuminate\Support\Facades\Route;
use Ninex\Lib\Exceptions\ApiExceptionRenderer;
use Ninex\Lib\Http\Middleware\ResetSqlRecord;
use Ninex\Lib\Support\SqlRecord;

class LibServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!$this->supportsIntegration()) {
            return;
        }
        $this->mergeConfigFrom(__DIR__.'/../config/ninexlib.php', 'ninexlib');
        $this->app->scoped(SqlRecord::class);
        $this->app->afterResolving(ExceptionHandler::class, function ($handler) {
            if (config('ninexlib.exceptions.enabled', true) && method_exists($handler, 'renderable')) {
                $handler->renderable(new ApiExceptionRenderer());
                if (method_exists($handler, 'ignore')) {
                    $handler->ignore(\Ninex\Lib\Core\ServiceException::class);
                }
            }
        });
    }

    public function boot(): void
    {
        if (!$this->supportsIntegration()) {
            return;
        }
        $this->publishes([__DIR__.'/../config/ninexlib.php' => config_path('ninexlib.php')], 'ninexlib-config');
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, MakeCrudCommand::class, \Ninex\Lib\Console\DoctorCommand::class]);
        }
        if (config('ninexlib.routes.enabled', true) && !$this->app->routesAreCached()) {
            Route::middleware(array_merge([\Ninex\Lib\Http\Middleware\UseJsonResponses::class], (array) config('ninexlib.routes.middleware', ['api'])))
                ->prefix(config('ninexlib.routes.prefix', 'api'))
                ->group(function () {
                    foreach (glob($this->app->basePath('routes/ninex/*.php')) ?: [] as $file) {
                        require $file;
                    }
                });
        }
        if (config('app.debug') && config('ninexlib.sql.enabled', false)) {
            SqlRecord::listen();
            $this->app->afterResolving(Kernel::class, fn ($kernel) => $kernel->pushMiddleware(ResetSqlRecord::class));
        }
    }
    /** Keep the pure core installable in hosts outside the tested Laravel integration range. */
    protected function supportsIntegration(): bool
    {
        return preg_match('/^(10|11|12|13)\./', $this->app->version()) === 1;
    }

}
