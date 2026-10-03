<?php

namespace Ninex\Lib\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Ninex\Lib\LibServiceProvider;

class GeneratedCrudTest extends TestCase
{
    public function testArtisanGeneratedResourceRunsValidationAndOwnerIsolation(): void
    {
        $root = sys_get_temp_dir().'/ninex-laravel-generated-'.bin2hex(random_bytes(8));
        mkdir($root.'/app', 0777, true);
        file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
        $loader = static function ($class) use ($root) {
            if (str_starts_with($class, 'App\\')) {
                $path = $root.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
                if (is_file($path)) {
                    require $path;
                }
            }
        };
        spl_autoload_register($loader);
        try {
            $this->app->setBasePath($root);
            $this->app->useAppPath($root.'/app');
            $fields = 'name:string,status:boolean,quantity:integer,note:text?,on_date:date?,at_time:datetime?';
            $this->artisan('ninexlib:make-crud', ['name' => 'NinexGeneratedRecord', '--fields' => $fields, '--table' => 'generated_records', '--route' => 'generated-records', '--dry-run' => true])->assertExitCode(0);
            $this->assertFileDoesNotExist($root.'/app/Models/NinexGeneratedRecord.php');
            $routeCache = $this->app->getCachedRoutesPath();
            if (!is_dir(dirname($routeCache))) {
                mkdir(dirname($routeCache), 0777, true);
            }
            file_put_contents($routeCache, '<?php // prior route cache');
            $this->artisan('ninexlib:make-crud', ['name' => 'NinexGeneratedRecord', '--fields' => $fields, '--table' => 'generated_records', '--route' => 'generated-records'])->assertExitCode(0);
            $controller = new \ReflectionClass(\App\Http\Controllers\NinexGeneratedRecordController::class);
            foreach (['index', 'show', 'store', 'update', 'destroy'] as $action) {
                $this->assertSame($controller->getName(), $controller->getMethod($action)->getDeclaringClass()->getName());
            }
            $service = new \ReflectionClass(\App\Services\NinexGeneratedRecordService::class);
            foreach (['paginate', 'show', 'store', 'update', 'destroy', 'validateForm', 'rules', 'scopeQuery', 'saving', 'saved', 'deleted'] as $method) {
                $this->assertSame($service->getName(), $service->getMethod($method)->getDeclaringClass()->getName());
            }
            $this->assertFileDoesNotExist($routeCache);
            $migration = glob($root.'/database/migrations/*_create_generated_records_table.php')[0];
            (require $migration)->up();
            config(['ninexlib.routes.prefix' => 'v2']);
            Route::middlewareGroup('api', []);
            $this->app->getProvider(LibServiceProvider::class)->boot();
            $url = '/v2/generated-records';
            $this->get($url)->assertUnauthorized()->assertJsonPath('code', 401);
            $this->actingAs(new GenericUser(['id' => 1]));
            $this->postJson($url, ['name' => 'missing bool'])->assertUnprocessable();
            $this->postJson($url, ['name' => 'out of range', 'status' => true, 'quantity' => 2147483648])->assertUnprocessable();
            $this->postJson($url, ['name' => 'invalid date', 'status' => false, 'quantity' => 0, 'on_date' => 'not-a-date'])->assertUnprocessable();
            $this->postJson($url, ['name' => 'spoof', 'status' => false, 'quantity' => 0, 'owner_id' => 2])->assertUnprocessable();
            $id = $this->postJson($url, ['name' => 'created', 'status' => false, 'quantity' => 0, 'note' => null, 'on_date' => '2026-10-02', 'at_time' => '2026-10-02 10:00:00'])->assertCreated()->assertJsonMissingPath('data.owner_id')->json('data.id');
            $this->getJson($url.'?filter[status]=0')->assertOk()->assertJsonPath('data.total', 1);
            $this->getJson($url.'/'.$id)->assertOk()->assertJsonPath('data.name', 'created');
            $this->putJson($url.'/'.$id, ['name' => 'updated'])->assertOk()->assertJsonPath('data.name', 'updated');
            $this->actingAs(new GenericUser(['id' => 2]));
            $this->getJson($url.'/'.$id)->assertNotFound();
            $this->putJson($url.'/'.$id, ['name' => 'stolen'])->assertNotFound();
            $this->deleteJson($url.'/'.$id)->assertNotFound();
            $this->actingAs(new GenericUser(['id' => 1]));
            $this->deleteJson($url.'/'.$id)->assertOk();
            config(['ninexlib.exceptions.legacy_http_200' => true]);
            $this->postJson($url, ['name' => 'legacy', 'status' => false, 'quantity' => 0])->assertOk()->assertJsonPath('data.name', 'legacy');
            $this->artisan('ninexlib:make-crud', ['name' => 'NinexGeneratedRecord', '--fields' => $fields, '--table' => 'generated_records', '--route' => 'generated-records'])->assertExitCode(1);
        } finally {
            spl_autoload_unregister($loader);
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
