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
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->postJson('/api/products', [])->assertUnprocessable();
        $this->postJson('/api/products', ['name' => 'x', 'owner_id' => 99])->assertUnprocessable();
        $id = $this->postJson('/api/products', ['name' => 'demo', 'status' => 0])->assertCreated()->assertJsonMissingPath('data.owner_id')->json('data.id');
        $this->assertEquals(1, Product::find($id)->owner_id);
        $this->getJson('/api/products?filter[status]=0')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.name', 'demo');
        $this->getJson('/api/products?filter[name]=em&filter[status]=0')->assertOk()->assertJsonPath('data.total', 1);
        $this->putJson('/api/products/'.$id, ['name' => 'updated'])->assertOk()->assertJsonPath('data.name', 'updated');
        $this->actingAs(new GenericUser(['id' => 2]));
        $this->getJson('/api/products/'.$id)->assertNotFound();
        $this->putJson('/api/products/'.$id, ['name' => 'stolen'])->assertNotFound();
        $this->actingAs(new GenericUser(['id' => 3]));
        $this->deleteJson('/api/products/'.$id)->assertNotFound();
        $this->getJson('/api/products/'.$id)->assertNotFound();
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->deleteJson('/api/products/'.$id)->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/products/'.$id)->assertNotFound();
    }
}
