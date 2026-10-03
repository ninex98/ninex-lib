<?php

use Illuminate\Support\Facades\Route;
use Ninex\Lib\Examples\Laravel\ProductController;

// Put this declaration in routes/api.php. The application supplies the API prefix.
Route::middleware('auth')->apiResource('products', ProductController::class);
