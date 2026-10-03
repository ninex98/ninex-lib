<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Ninex\Lib\Core\Page;
use Ninex\Lib\Core\Query;
use Ninex\Lib\Core\Result;
use Ninex\Lib\Core\ServiceException;

if (class_exists('Illuminate\Foundation\Application') || class_exists('think\App') || class_exists('Symfony\Component\HttpKernel\Kernel')) {
    throw new RuntimeException('Core isolation check must run without a framework.');
}
$query = Query::fromArray(['filter' => ['status' => 0]], ['status'], ['id']);
$body = Result::success((new Page([], 0, $query->pageSize, $query->page))->toArray());
$error = new ServiceException('conflict', 10001, null, httpStatus: 409);
if ($query->filters !== ['status' => 0] || $body['data']['data'] !== [] || $error->getHttpStatus() !== 409) {
    throw new RuntimeException('Core smoke check failed.');
}
echo "Core-only bootstrap OK (no framework installed).\n";
