<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 基础配置
    |--------------------------------------------------------------------------
    */
    'http' => [
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
