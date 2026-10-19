<?php

declare(strict_types=1);

return [

    'dish_name_invalid' => 'El nombre del plato de la línea es obligatorio y no puede superar los :max caracteres.',
    'dish_repeated' => 'Un plato solo puede aparecer en una línea del pedido.',
    'expire_orders_description' => 'Cancela los pedidos de todas las sodas cuyo plazo de pago o de confirmación ya pasó.',
    'lines_out_of_range' => 'El pedido debe tener entre :min y :max líneas.',
    'order_not_found' => 'El pedido no existe.',
    'order_not_paid' => 'El pedido no se puede confirmar hasta que su pago esté aprobado.',
    'orders_expired' => '{0} No había pedidos vencidos.|{1} Se canceló 1 pedido vencido.|[2,*] Se cancelaron :count pedidos vencidos.',
    'portions_sold_out' => 'No quedan suficientes porciones de un plato del pedido.',
    'quantity_out_of_range' => 'La cantidad de cada línea debe estar entre :min y :max.',
    'transition_not_allowed' => 'El pedido no puede pasar a ese estado.',
    'unit_price_out_of_range' => 'El precio unitario debe estar entre ₡:min y ₡:max.',

];
