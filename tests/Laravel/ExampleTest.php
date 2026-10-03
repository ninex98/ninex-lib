<?php

namespace Ninex\Lib\Tests;

use Illuminate\Auth\GenericUser;
use Ninex\Lib\Examples\Laravel\Product;

class ExampleTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->prefix('api')->group(dirname(__DIR__, 2).'/examples/laravel/routes.php');
    }
    protected function setUp(): void
    {
        parent::setUp();
        (require dirname(__DIR__, 2).'/examples/laravel/migration.php')->up();
    }

    public function testDocumentedExampleWorksThroughHttpRoutes(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->actingAs(new GenericUser(['id' => 1, 'tenant_id' => 10, 'role' => 'editor']));
        $this->postJson('/api/products', [])->assertUnprocessable();
        $this->postJson('/api/products', ['name' => 'x', 'tenant_id' => 99])->assertUnprocessable();
        $id = $this->postJson('/api/products', ['name' => 'demo', 'status' => 0])->assertCreated()->assertJsonMissingPath('data.tenant_id')->json('data.id');
        $this->assertEquals(10, Product::find($id)->tenant_id);
        $this->getJson('/api/products?filter[status]=0')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.name', 'demo');
        $this->putJson('/api/products/'.$id, ['name' => 'updated'])->assertOk()->assertJsonPath('data.name', 'updated');
        $this->actingAs(new GenericUser(['id' => 2, 'tenant_id' => 20, 'role' => 'editor']));
        $this->getJson('/api/products/'.$id)->assertNotFound();
        $this->putJson('/api/products/'.$id, ['name' => 'stolen'])->assertNotFound();
        $this->actingAs(new GenericUser(['id' => 3, 'tenant_id' => 10, 'role' => 'viewer']));
        $this->deleteJson('/api/products/'.$id)->assertForbidden();
        $this->getJson('/api/products/'.$id)->assertOk();
        $this->actingAs(new GenericUser(['id' => 1, 'tenant_id' => 10, 'role' => 'editor']));
        $this->deleteJson('/api/products/'.$id)->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/products/'.$id)->assertNotFound();
    }
}
