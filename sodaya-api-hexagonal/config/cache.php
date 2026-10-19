<?php

declare(strict_types=1);

return [

    /*
    | Store used when none is named. The database store keeps the cache in the "cache" table of PostgreSQL,
    | shared by every process of the application; tests, development and production use the same store.
    */
    'default' => env('CACHE_STORE', 'database'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => 'cache_locks',
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

    ],

    /*
    | Prefix of every key, so that another application sharing the store cannot collide with this one.
    */
    'prefix' => 'sodaya-cache-',

    /*
    | Classes an item read from the cache may be unserialized into. With false, only scalars and arrays
    | come back: an object put in the cache is not rebuilt, which is why the menu is cached as arrays.
    */
    'serializable_classes' => false,

];
