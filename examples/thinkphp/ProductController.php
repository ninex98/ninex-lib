<?php

namespace Ninex\Lib\Examples\ThinkPhp;

use think\DbManager;
use think\Request;
use Ninex\Lib\ThinkPhp\CrudController;

class ProductController extends CrudController
{
    public function __construct(Request $request, DbManager $db)
    {
        // ThinkPHP caches container-resolved dependencies; identity-bound services must be fresh.
        parent::__construct($request, fn () => new ProductService($db, $request));
    }
}
