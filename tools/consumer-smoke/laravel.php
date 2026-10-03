<?php

require getcwd().'/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Route};
use Ninex\Lib\Core\ServiceException;
use Ninex\Lib\LibServiceProvider;
use Ninex\Lib\Scaffolding\{CrudGenerator, FileWriter, ResourceDefinition};

foreach (['PHPUnit\Framework\TestCase', 'Orchestra\Testbench\TestCase', 'think\App'] as $class) {
    if (class_exists($class)) {
        throw new RuntimeException('Unexpected dependency: '.$class);
    }
}
foreach (['bootstrap/cache', 'config', 'storage/framework/views', 'storage/logs'] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
}
file_put_contents('bootstrap/providers.php', '<?php return [];');
file_put_contents('config/app.php', '<?php return ["debug"=>false,"key"=>"base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=","providers"=>Illuminate\\Support\\ServiceProvider::defaultProviders()->toArray()];');
file_put_contents('config/logging.php', '<?php return ["default"=>"null","channels"=>["null"=>["driver"=>"monolog","handler"=>Monolog\\Handler\\NullHandler::class]]];');
file_put_contents('config/database.php', '<?php return ["default"=>"sqlite","connections"=>["sqlite"=>["driver"=>"sqlite","database"=>":memory:","prefix"=>""]]];');
file_put_contents('config/auth.php', '<?php return ["defaults"=>["guard"=>"web"],"guards"=>["web"=>["driver"=>"session","provider"=>"users"]],"providers"=>["users"=>["driver"=>"database","table"=>"users"]]];');
file_put_contents('config/session.php', '<?php return ["driver"=>"array","cookie"=>"ninex_smoke","lifetime"=>120,"lottery"=>[0,1]];');
$plan = (new CrudGenerator())->plan(new ResourceDefinition('InstalledProduct', 'laravel', 'name:string,status:boolean', 'installed_products'), getcwd());
(new FileWriter())->write(getcwd(), $plan);
// The same conventional application bootstrap works on Laravel 10–13.
$app = new Application(getcwd());
$app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
$app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
Route::middlewareGroup('api', []);
Route::aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);
Route::get('api/conflict', fn () => throw new ServiceException('Conflict', 10001, ['remaining' => 0], httpStatus: 409));
$response = $kernel->handle(Request::create('/api/conflict', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
$body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
if (!$app->getProvider(LibServiceProvider::class) || $response->getStatusCode() !== 409 || $body['code'] !== 10001) {
    throw new RuntimeException('Automatic provider/exception registration failed: '.$response->getContent());
}
foreach (array_keys($plan) as $path) {
    if (str_starts_with($path, 'database/migrations/')) {
        (require $path)->up();
    }
}
Auth::guard()->setUser(new Illuminate\Auth\GenericUser(['id' => 1]));
$request = Request::create('/api/installed_products', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], '{"name":"generated","status":false}');
$created = $kernel->handle($request);
if ($created->getStatusCode() !== 201) {
    throw new RuntimeException('Installed generated route failed: '.$created->getContent());
}
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
if ($console->call('ninexlib:install') !== 0 || !is_file('config/ninexlib.php')) {
    throw new RuntimeException('Installer failed.');
}
if ($console->call('ninexlib:doctor') !== 0) {
    throw new RuntimeException('Installed native doctor failed.');
}
echo "laravel: discovery, generated migration/route/CRUD, errors and installer OK\n";
