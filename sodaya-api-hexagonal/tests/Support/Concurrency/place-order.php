<?php

declare(strict_types=1);

/*
 * One customer of a race: orders one portion of a dish and prints «placed» or «sold-out».
 * Arguments: gate, soda, customer, dish.
 */

use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

[, $sodaId, $customerId, $dishId] = require __DIR__.'/boot.php';

try {
    app(SodaTransaction::class)->run($sodaId, fn () => app(PlaceOrder::class)->execute(
        new PlaceOrderCommand($customerId, $sodaId, [['dish_id' => $dishId, 'quantity' => 1]], "carrera-{$customerId}"),
        new DateTimeImmutable,
    ));
    echo 'placed';
} catch (PortionsSoldOutException) {
    echo 'sold-out';
}
