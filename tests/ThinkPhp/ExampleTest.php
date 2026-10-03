<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Examples\ThinkPhp\{ProductController};
use think\DbManager;
use think\Request;

class ExampleTest extends TestCase
{
    public function testExampleControllersUseValidationAuthorizationAndTenantScope(): void
    {
        $db = new DbManager();
        $db->setConfig($this->databaseFixture->think());
        $db->connect()->execute($this->databaseFixture->createTableSql('products'));
        $request = (new Request())->withHeader(['content-type' => 'application/json'])->withMiddleware(['actor' => ['id' => 1, 'tenant_id' => 10, 'role' => 'editor']])->withInput('{"name":"demo","status":0}');
        $controller = new ProductController($request, $db);
        $response = $controller->save();
        $this->assertSame(201, $response->getCode());
        $id = $response->getData()['data']['id'];
        $request->withGet(['filter' => ['status' => 0]]);
        $this->assertSame(1, $controller->index()->getData()['data']['total']);
        $request->withInput('{"name":"changed"}');
        $this->assertSame('changed', $controller->update($id)->getData()['data']['name']);
        $request->withInput('{"name":""}');
        $this->assertSame(422, $controller->update($id)->getCode());
        $request->withMiddleware(['actor' => ['id' => 2, 'tenant_id' => 20, 'role' => 'editor']]);
        $other = new ProductController($request, $db);
        $this->assertSame(404, $other->read($id)->getCode());
        $request->withMiddleware(['actor' => ['id' => 1, 'tenant_id' => 10, 'role' => 'editor']]);
        $this->assertSame([], $controller->delete($id)->getData()['data']);
        $this->assertSame(0, $db->table('products')->count());
    }
}
