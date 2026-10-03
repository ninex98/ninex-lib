<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use think\App;
use think\DbManager;
use think\Request;

class RouteTest extends TestCase
{
    public function testExampleRunsThroughThinkPhpHttpKernel(): void
    {
        $root = sys_get_temp_dir().'/ninex-think-test-'.bin2hex(random_bytes(8)).'/';
        mkdir($root.'app', 0777, true);
        mkdir($root.'route');
        $examples = dirname(__DIR__, 2).'/examples/thinkphp/';
        copy($examples.'provider.php', $root.'app/provider.php');
        copy($examples.'routes.php', $root.'route/app.php');
        $app = new class ($root) extends App {
            // PHPUnit owns PHP's global error handlers; use ThinkPHP's normal HTTP exception pipeline.
            protected $initializers = [\think\initializer\RegisterService::class, \think\initializer\BootService::class];
        };
        try {
            $app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => $root.'runtime/cache/']]], 'cache');
            $app->config->set(['default' => 'file', 'channels' => ['file' => ['type' => 'File', 'path' => $root.'runtime/log/']]], 'log');
            $app->initialize();
            $db = new DbManager();
            $db->setConfig($this->databaseFixture->think());
            $db->connect()->execute($this->databaseFixture->createTableSql('products'));
            $app->instance(DbManager::class, $db);
            $send = function ($method, $path, $body = [], $actor = null) use ($app) {
                $request = (new Request())->withServer(['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'.$path])->setPathinfo($path)
                    ->withHeader(['accept' => 'application/json', 'content-type' => 'application/json'])
                    ->withInput(json_encode($body))->withMiddleware(['actor' => $actor]);
                return $app->http->run($request);
            };
            $actor = ['id' => 1, 'tenant_id' => 10, 'role' => 'editor'];
            $this->assertSame(401, $send('GET', 'api/products')->getCode());
            $this->assertSame(422, $send('POST', 'api/products', [], $actor)->getCode());
            $created = $send('POST', 'api/products', ['name' => 'demo'], $actor);
            $this->assertSame(201, $created->getCode(), json_encode($created->getData()));
            $id = $created->getData()['data']['id'];
            $updated = $send('PUT', 'api/products/'.$id, ['name' => 'updated'], $actor);
            $this->assertSame(200, $updated->getCode(), json_encode($updated->getData()));
            $this->assertSame('updated', $updated->getData()['data']['name']);
            $this->assertSame(404, $send('GET', 'api/products/'.$id, [], ['id' => 2, 'tenant_id' => 20, 'role' => 'editor'])->getCode());
            $this->assertSame(403, $send('DELETE', 'api/products/'.$id, [], ['id' => 3, 'tenant_id' => 10, 'role' => 'viewer'])->getCode());
            $this->assertSame(200, $send('DELETE', 'api/products/'.$id, [], $actor)->getCode());
            $this->assertSame(0, $db->table('products')->count());
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
