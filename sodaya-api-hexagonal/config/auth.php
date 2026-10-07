<?php

declare(strict_types=1);

use Src\Identity\Infrastructure\Persistence\Models\UserModel;

return [

    /*
    | API sin sesiones: el guard por defecto es el de tokens de Sanctum.
    */
    'defaults' => [
        'guard' => 'sanctum',
        'passwords' => 'users',
    ],

    'guards' => [
        // Con provider, Sanctum exige que el dueño del token sea un UserModel.
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => UserModel::class,
        ],
    ],

    /*
    | Recuperación de contraseña (#93): el enlace vale 60 minutos.
    | La tabla password_reset_tokens se crea en ese issue.
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
