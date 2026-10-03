<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 基础配置
    |--------------------------------------------------------------------------
    */
    'routes' => ['enabled' => true, 'prefix' => 'api', 'middleware' => ['api']],
    'model_cache' => ['enabled' => false],
    'exceptions' => ['enabled' => true, 'legacy_http_200' => false],
    'sql' => ['enabled' => false, 'expose' => false, 'limit' => 100],
    'pagination' => ['max_page_size' => 100],
    'http' => [
        'verify' => true,
        'timeout' => env('NINEX_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('NINEX_HTTP_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | 文件处理配置
    |--------------------------------------------------------------------------
    */
    'file' => [
        'disk' => env('NINEX_FILE_DISK', 'public'),
        'path' => env('NINEX_FILE_PATH', 'uploads'),
        'allowed_types' => [
            'image' => ['jpg', 'jpeg', 'png', 'gif'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        ],
        'max_size' => env('NINEX_FILE_MAX_SIZE', 10240), // KB
    ],
];
