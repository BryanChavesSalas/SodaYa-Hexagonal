<?php

declare(strict_types=1);
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [

    'api_path' => 'api/v1',

    'export_path' => 'openapi/v1.json',

    'info' => [
        'version' => '1.0.0',
        'description' => 'API de SodaYa: menú del día y pedidos para llevar de varias sodas.',
    ],

    'ui' => [
        'title' => 'SodaYa API',
    ],

    'middleware' => [],

    'security_strategy' => MiddlewareAuthSecurityStrategy::class,

];
