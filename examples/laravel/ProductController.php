<?php

namespace Ninex\Lib\Examples\Laravel;

use Ninex\Lib\Http\Controllers\LibController;

class ProductController extends LibController
{
    protected ?string $serviceClass = ProductService::class;
}
