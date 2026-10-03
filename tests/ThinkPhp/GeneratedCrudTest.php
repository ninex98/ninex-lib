<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Scaffolding\{CrudGenerator, FileWriter, ResourceDefinition};
use think\{App, DbManager, Request};

class GeneratedCrudTest extends TestCase
{
    public function testGeneratedPhpAndSqlRunThroughTheThinkPhpHttpKernel(): void
    {
        $root = sys_get_temp_dir().'/ninex-think-generated-'.bin2hex(random_bytes(8)).'/';
        mkdir($root.'app', 0777, true);
        $definition = new ResourceDefinition('NinexGeneratedRecord', 'thinkphp', 'name:string,status:boolean,quantity:integer,note:text?,on_date:date?,at_time:datetime?', 'generated_records', 'generated-records', 'GeneratedThinkApp');
        (new FileWriter())->write($root, (new CrudGenerator())->plan($definition, $root));
        $loader = static function ($class) use ($root) {
            if (str_starts_with($class, 'GeneratedThinkApp\\')) {
                $file = $root.'app/'.str_replace('\\', '/', substr($class, strlen('GeneratedThinkApp\\'))).'.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        };
        spl_autoload_register($loader);
        $app = new class ($root) extends App {
            protected $initializers = [\think\initializer\RegisterService::class, \think\initializer\BootService::class];
        };
        try {
            $app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => $root.'runtime/cache/']]], 'cache');
            $app->config->set(['default' => 'file', 'channels' => ['file' => ['type' => 'File', 'path' => $root.'runtime/log/']]], 'log');
            $app->initialize();
            $db = new DbManager();
            $db->setConfig($this->databaseFixture->think());
            $sql = file_get_contents($root.'database/ninex/generated_records.'.$this->databaseFixture->driver().'.sql');
            foreach (explode(';', $sql) as $statement) {
                if (trim($statement) !== '') {
                    $db->connect()->execute($statement);
                }
            }
            $app->instance(DbManager::class, $db);
            $send = function ($method, $path, $body = [], $owner = null) use ($app) {
                $request = (new Request())->withServer(['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'.$path])->setPathinfo($path)
                    ->withHeader(['accept' => 'application/json', 'content-type' => 'application/json'])->withInput(json_encode($body))
                    ->withMiddleware(['actor' => $owner === null ? null : ['id' => $owner]]);
                return $app->http->run($request);
            };
            $url = 'api/generated-records';
            $this->assertSame(401, $send('GET', $url)->getCode());
            $this->assertSame(422, $send('POST', $url, ['name' => 'missing bool'], 1)->getCode());
            $this->assertSame(422, $send('POST', $url, ['name' => 'out of range', 'status' => true, 'quantity' => 2147483648], 1)->getCode());
            $this->assertSame(422, $send('POST', $url, ['name' => 'invalid date', 'status' => false, 'quantity' => 0, 'on_date' => 'not-a-date'], 1)->getCode());
            $this->assertSame(422, $send('POST', $url, ['name' => 'spoof', 'status' => false, 'quantity' => 0, 'owner_id' => 2], 1)->getCode());
            $created = $send('POST', $url, ['name' => 'created', 'status' => false, 'quantity' => 0, 'note' => null, 'on_date' => '2026-10-02', 'at_time' => '2026-10-02 10:00:00'], 1);
            $this->assertSame(201, $created->getCode(), json_encode($created->getData()));
            $id = $created->getData()['data']['id'];
            $updated = $send('PUT', $url.'/'.$id, ['name' => 'updated'], 1);
            $this->assertSame(200, $updated->getCode(), json_encode($updated->getData()));
            $this->assertSame('updated', $updated->getData()['data']['name']);
            foreach (['GET', 'PUT', 'DELETE'] as $method) {
                $this->assertSame(404, $send($method, $url.'/'.$id, ['name' => 'stolen'], 2)->getCode());
            }
            $this->assertSame(200, $send('DELETE', $url.'/'.$id, [], 1)->getCode());
            $this->assertSame(0, $db->table('generated_records')->count());
            $app->config->set(['legacy_http_200' => true], 'ninexlib');
            $this->assertSame(200, $send('POST', $url, ['name' => 'legacy', 'status' => false, 'quantity' => 0], 1)->getCode());
            $unauthorized = $send('GET', $url);
            $this->assertSame(200, $unauthorized->getCode());
            $this->assertSame(401, $unauthorized->getData()['code']);
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
