<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Core\Tests\DatabaseFixture;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected DatabaseFixture $databaseFixture;
    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseFixture = new DatabaseFixture();
    }
    protected function tearDown(): void
    {
        try {
            $this->databaseFixture->close();
        } finally {
            parent::tearDown();
        }
    }
}
