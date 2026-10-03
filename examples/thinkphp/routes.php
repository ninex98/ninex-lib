<?php

use think\facade\Route;
use Ninex\Lib\Examples\ThinkPhp\ProductController;

// ProductService rejects requests without a trusted actor supplied by your authentication middleware.
Route::resource('api/products', ProductController::class)->only(['index', 'read', 'save', 'update', 'delete']);
