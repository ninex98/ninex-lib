<?php

namespace Ninex\Lib\Core\Tests;

use Ninex\Lib\Core\{Page, Query, Result, ServiceException};
use PHPUnit\Framework\TestCase;

class CoreTest extends TestCase
{
    public function testResultsPreserveEmptyValuesAndBusinessCodeIsIndependentOfHttp(): void
    {
        foreach ([[], false, '', 0, null] as $value) {
            $this->assertSame($value, Result::success($value)['data']);
        }
        $e = new ServiceException('conflict', 10001, ['field' => 'name'], null, 409);
        $this->assertSame(409, $e->getHttpStatus());
        $this->assertSame(10001, Result::error($e)['code']);
        $this->assertSame(['field' => 'name'], Result::error($e)['data']);
    }

    public function testQueryPreservesFalseZeroNullAndRejectsOverflow(): void
    {
        $query = Query::fromArray(['filter' => ['a' => false, 'b' => 0, 'c' => null]], ['a', 'b', 'c'], []);
        $this->assertSame(['a' => false, 'b' => 0, 'c' => null], $query->filters);
        $this->expectException(ServiceException::class);
        Query::fromArray(['page' => (string) PHP_INT_MAX, 'page_size' => 2], [], []);
    }

    public function testCoreDoesNotRequireFrameworkAutoloading(): void
    {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        $this->assertSame(['php'], array_keys($composer['require']));
        $this->assertSame(['data' => [], 'total' => 0, 'page_size' => 15, 'current_page' => 1, 'total_pages' => 1], (new Page([], 0, 15, 1))->toArray());
    }
}
