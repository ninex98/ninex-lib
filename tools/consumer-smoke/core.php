<?php

require getcwd().'/vendor/autoload.php';

use Ninex\Lib\Core\{Page, Query, Result, ServiceException};

foreach (['Illuminate\Foundation\Application', 'think\App', 'Symfony\Component\HttpKernel\Kernel', 'PHPUnit\Framework\TestCase'] as $class) {
    if (class_exists($class)) {
        throw new RuntimeException('Unexpected dependency: '.$class);
    }
}
$query = Query::fromArray(['filter' => ['status' => false]], ['status'], ['id']);
$result = Result::success((new Page([], 0, $query->pageSize, 1))->toArray());
if ($query->filters !== ['status' => false] || $result['data']['data'] !== [] || (new ServiceException('Conflict', 10001, null, httpStatus: 409))->getHttpStatus() !== 409) {
    throw new RuntimeException('Core runtime smoke failed.');
}
echo "core: standalone production install OK\n";
