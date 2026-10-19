<?php

declare(strict_types=1);

return [

    'order_ready' => [
        'subject' => 'Su pedido :number está listo',
        'message' => 'Hola, :name. Su pedido con número de retiro :number ya está listo: puede pasar a retirarlo al mostrador.',
    ],
    'order_cancelled' => [
        'subject' => 'Su pedido :number fue cancelado',
        'message' => 'Hola, :name. Su pedido con número de retiro :number fue cancelado :origin.:refund',
    ],
    'origins' => [
        'customer' => 'por usted',
        'kitchen' => 'por la cocina de la soda',
        'payment_timeout' => 'porque no se recibió su pago a tiempo',
        'confirmation_timeout' => 'porque la cocina no lo confirmó a tiempo',
    ],
    'refund' => ' Se le reembolsarán los ₡:amount que pagó.',
    'no_refund' => ' No había ningún pago que devolver.',

];
