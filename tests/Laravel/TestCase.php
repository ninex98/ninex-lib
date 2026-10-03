<?php

namespace Ninex\Lib\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ninex\Lib\LibServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    private ?\Ninex\Lib\Core\Tests\DatabaseFixture $databaseFixture = null;

    protected function getPackageProviders($app): array
    {
        return [LibServiceProvider::class];
    }
    protected function defineEnvironment($app): void
    {
        $this->databaseFixture = new \Ninex\Lib\Core\Tests\DatabaseFixture();
        $app['config']->set('app.debug', false);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseFixture->laravel('primary'));
        $app['config']->set('database.connections.secondary', $this->databaseFixture->laravel('secondary'));
        $app['config']->set('cache.default', 'array');
    }
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['testing', 'secondary'] as $connection) {
            Schema::connection($connection)->create('items', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('status')->default(0);
                $table->integer('tenant_id')->default(1);
            });
        }
    }
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->databaseFixture?->close();
        }
    }

}
