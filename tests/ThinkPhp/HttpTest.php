<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Core\ServiceException;
use Ninex\Lib\ThinkPhp\ExceptionHandler;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Request;

class HttpTest extends TestCase
{
    public function testExceptionMappingPreservesBusinessDataAndHeaders(): void
    {
        $app = new App();
        $request = (new Request())->setPathinfo('api/products');
        $handler = new ExceptionHandler($app);
        $response = $handler->render($request, new ServiceException('conflict', 10001, ['field' => 'name'], null, 409));
        $this->assertSame(409, $response->getCode());
        $this->assertSame(['code' => 10001, 'message' => 'conflict', 'data' => ['field' => 'name']], $response->getData());
        $limited = $handler->render($request, new \think\exception\HttpException(429, 'limited', null, ['Retry-After' => 30]));
        $this->assertSame(429, $limited->getCode());
        $this->assertEquals(30, $limited->getHeader('Retry-After'));
        $failure = $handler->render($request, new \RuntimeException('private'));
        $this->assertSame(500, $failure->getCode());
        $this->assertSame('服务器内部错误', $failure->getData()['message']);
        $app->config->set(['legacy_http_200' => true], 'ninexlib');
        $this->assertSame(200, $handler->render($request, new ServiceException('bad', 422))->getCode());
    }
}
