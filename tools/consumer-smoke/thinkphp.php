<?php

require getcwd().'/vendor/autoload.php';

use Ninex\Lib\Core\{CrudService, ServiceException};
use Ninex\Lib\ThinkPhp\{ExceptionHandler, ThinkOrmRepository};
use think\{App, DbManager, Request};

foreach (['Illuminate\Foundation\Application', 'Symfony\Component\HttpKernel\Kernel', 'PHPUnit\Framework\TestCase'] as $class) {
    if (class_exists($class)) {
        throw new RuntimeException('Unexpected dependency: '.$class);
    }
}
$db = new DbManager();
$db->setConfig(['default' => 'sqlite', 'connections' => ['sqlite' => ['type' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]]);
$db->connect()->execute('CREATE TABLE records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
$service = new CrudService(new ThinkOrmRepository($db, 'records', ['id', 'name']), ['name'], [], ['id'], fn ($operation, $data) => $data, fn () => true);
$record = $service->store(['name' => 'installed']);
if ($service->show($record['id'])['name'] !== 'installed') {
    throw new RuntimeException('ThinkORM installation failed.');
}
$response = (new ExceptionHandler(new App(getcwd().'/')))->render((new Request())->setPathinfo('api/records'), new ServiceException('Conflict', 10001, null, httpStatus: 409));
if ($response->getCode() !== 409 || $response->getData()['code'] !== 10001) {
    throw new RuntimeException('ThinkPHP error mapping failed.');
}
echo "thinkphp: production install, native ORM CRUD and error mapping OK\n";

// Exercise the installed package's service metadata and templates in a conventional host.
foreach (['app', 'config', 'runtime'] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
}
file_put_contents('config/cache.php', '<?php return ["default"=>"file","stores"=>["file"=>["type"=>"File","path"=>getcwd()."/runtime/cache/"]]];');
file_put_contents('config/log.php', '<?php return ["default"=>"file","channels"=>["file"=>["type"=>"File","path"=>getcwd()."/runtime/log/"]]];');
$first = new App(getcwd().'/');
$first->initialize();
$first->console->call('service:discover');
$app = new App(getcwd().'/');
$app->initialize();
$make = $app->console->find('ninexlib:make-crud');
$status = $make->run(new \think\console\Input(['ninexlib:make-crud', 'InstalledProduct', '--fields=name:string,status:boolean', '--table=installed_products']), new \think\console\Output('buffer'));
if ($status !== 0) {
    throw new RuntimeException('Installed native generation failed.');
}
foreach (explode(';', file_get_contents('database/ninex/installed_products.sqlite.sql')) as $statement) {
    if (trim($statement) !== '') {
        $db->connect()->execute($statement);
    }
}
$app->instance(DbManager::class, $db);
$send = function ($actor) use ($app) {
    $request = (new Request())->withServer(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/installed_products'])->setPathinfo('api/installed_products')
        ->withHeader(['accept' => 'application/json', 'content-type' => 'application/json'])->withInput('{"name":"installed","status":false}')
        ->withMiddleware(['actor' => $actor]);
    return $app->http->run($request);
};
if ($send(null)->getCode() !== 401 || $send(['id' => 1])->getCode() !== 201) {
    throw new RuntimeException('Installed generated HTTP route failed.');
}
$doctor = $app->console->find('ninexlib:doctor');
if ($doctor->run(new \think\console\Input(['ninexlib:doctor', '--strict']), new \think\console\Output('buffer')) !== 0) {
    throw new RuntimeException('Installed native doctor failed.');
}
echo "thinkphp: service discovery, native commands and generated HTTP CRUD OK\n";
