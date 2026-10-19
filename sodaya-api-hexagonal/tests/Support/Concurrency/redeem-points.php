<?php

declare(strict_types=1);

/*
 * One redemption of a race: spends points of a customer and prints «redeemed» or «insufficient».
 * Arguments: gate, customer, points.
 */

use Src\Loyalty\Points\Application\DTOs\RedeemPointsCommand;
use Src\Loyalty\Points\Application\UseCases\RedeemPoints;
use Src\Loyalty\Points\Domain\Exceptions\InsufficientPointsException;

[, $customerId, $points] = require __DIR__.'/boot.php';

try {
    app(RedeemPoints::class)->execute(new RedeemPointsCommand($customerId, (int) $points), new DateTimeImmutable);
    echo 'redeemed';
} catch (InsufficientPointsException) {
    echo 'insufficient';
}
