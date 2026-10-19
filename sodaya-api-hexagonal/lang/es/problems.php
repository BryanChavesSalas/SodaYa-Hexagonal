<?php

declare(strict_types=1);

return [

    'solicitud-invalida' => [
        'title' => 'Solicitud inválida',
        'detail' => 'La solicitud no se puede procesar tal como viene.',
    ],
    'no-autenticado' => [
        'title' => 'No autenticado',
        'detail' => 'Debe iniciar sesión para realizar esta acción.',
    ],
    'credenciales-invalidas' => [
        'title' => 'Credenciales inválidas',
        'detail' => 'El correo o la contraseña no son correctos.',
    ],
    'prohibido' => [
        'title' => 'Acción no permitida',
        'detail' => 'No tiene permiso para realizar esta acción.',
    ],
    'no-encontrado' => [
        'title' => 'Recurso no encontrado',
        'detail' => 'El recurso solicitado no existe.',
    ],
    'pedido-no-encontrado' => [
        'title' => 'Pedido no encontrado',
        'detail' => 'El pedido no existe.',
    ],
    'metodo-no-permitido' => [
        'title' => 'Método no permitido',
        'detail' => 'La ruta no acepta el método HTTP usado.',
    ],
    'conflicto' => [
        'title' => 'Conflicto',
        'detail' => 'La solicitud choca con el estado actual del recurso.',
    ],
    'transicion-no-permitida' => [
        'title' => 'Transición no permitida',
        'detail' => 'El pedido no puede pasar a ese estado.',
    ],
    'pedido-sin-pago' => [
        'title' => 'Pedido sin pago',
        'detail' => 'El pedido no se puede confirmar hasta que su pago esté aprobado.',
    ],
    'porciones-agotadas' => [
        'title' => 'Porciones agotadas',
        'detail' => 'No quedan suficientes porciones de un plato del pedido.',
    ],
    'puntos-insuficientes' => [
        'title' => 'Puntos insuficientes',
        'detail' => 'El saldo de puntos no alcanza para el canje.',
    ],
    'pedido-ya-pagado' => [
        'title' => 'Pedido ya pagado',
        'detail' => 'El pedido ya tiene un pago aprobado.',
    ],
    'pedido-no-pagable' => [
        'title' => 'Pedido no pagable',
        'detail' => 'Solo se puede pagar un pedido pendiente.',
    ],
    'pago-rechazado' => [
        'title' => 'Pago rechazado',
        'detail' => 'El proveedor rechazó el pago. Puede intentar de nuevo mientras el pedido siga pendiente.',
    ],
    'datos-invalidos' => [
        'title' => 'Datos inválidos',
        'detail' => 'Los datos enviados no son válidos.',
    ],
    'pedido-vacio' => [
        'title' => 'Pedido vacío',
        'detail' => 'El pedido debe tener al menos una línea.',
    ],
    'cantidad-invalida' => [
        'title' => 'Cantidad inválida',
        'detail' => 'La cantidad de cada línea debe estar entre 1 y 20.',
    ],
    'plato-no-pertenece-a-la-soda' => [
        'title' => 'Plato de otra soda',
        'detail' => 'Un plato del pedido no pertenece a la soda.',
    ],
    'plato-no-disponible' => [
        'title' => 'Plato no disponible',
        'detail' => 'Un plato del pedido no está disponible.',
    ],
    'soda-cerrada' => [
        'title' => 'Soda cerrada',
        'detail' => 'La soda no recibe pedidos en este momento.',
    ],
    'promocion-traslapada' => [
        'title' => 'Promoción traslapada',
        'detail' => 'La promoción se traslapa con otra del mismo día.',
    ],
    'demasiadas-solicitudes' => [
        'title' => 'Demasiadas solicitudes',
        'detail' => 'Superó el límite de solicitudes. Intente de nuevo más tarde.',
    ],
    'error-interno' => [
        'title' => 'Error interno',
        'detail' => 'Ocurrió un error inesperado. Intente de nuevo más tarde.',
    ],
    'servicio-no-disponible' => [
        'title' => 'Servicio no disponible',
        'detail' => 'El servicio no está disponible en este momento.',
    ],

];
