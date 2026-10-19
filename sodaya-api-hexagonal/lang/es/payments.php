<?php

declare(strict_types=1);

return [

    'amount_not_positive' => 'El monto del pago debe ser un número entero de colones mayor que cero.',
    'charge_reference_invalid' => 'La referencia del proveedor es obligatoria y no puede superar los :max caracteres.',
    'charge_result_invalid' => 'Un cobro aprobado lleva la referencia del proveedor y uno rechazado, el código del rechazo.',
    'gateway_failed' => 'El proveedor de pagos respondió con un error (:status). Intente de nuevo en unos segundos.',
    'gateway_unreachable' => 'El proveedor de pagos no respondió. Intente de nuevo en unos segundos.',
    'idempotency_key_invalid' => 'La clave de idempotencia del cobro es obligatoria.',
    'payment_method_invalid' => 'El método de pago es obligatorio.',

];
