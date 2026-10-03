<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Examples\ThinkPhp\{ProductController};
use think\DbManager;
use think\Request;

class ExampleTest extends TestCase
{
    public function testExampleControllersWorkWithoutTenantOrRoleAttributes(): void
    {
        $db = new DbManager();
        $db->setConfig($this->databaseFixture->think());
        $db->connect()->execute(str_replace('tenant_id', 'owner_id', $this->databaseFixture->createTableSql('products')));
        $request = (new Request())->withHeader(['content-type' => 'application/json'])->withMiddleware(['actor' => ['id' => 1]])->withInput('{"name":"demo","status":0}');
        $controller = new ProductController($request, $db);
        $response = $controller->save();
        $this->assertSame(201, $response->getCode());
        $id = $response->getData()['data']['id'];
        $request->withGet(['filter' => ['name' => 'em', 'status' => 0]]);
        $this->assertSame(1, $controller->index()->getData()['data']['total']);
        $request->withInput('{"name":"changed"}');
        $this->assertSame('changed', $controller->update($id)->getData()['data']['name']);
        $request->withInput('{"name":""}');
        $this->assertSame(422, $controller->update($id)->getCode());
        $request->withMiddleware(['actor' => ['id' => 2]]);
        $other = new ProductController($request, $db);
        $this->assertSame(404, $other->read($id)->getCode());
        $request->withMiddleware(['actor' => ['id' => 1]]);
        $this->assertSame([], $controller->delete($id)->getData()['data']);
        $this->assertSame(0, $db->table('products')->count());
    }
}
