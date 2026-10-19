<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Identity;

use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Application\Contracts\Recipients;
use Src\Ordering\Orders\Application\DTOs\Recipient;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;

final readonly class IdentityRecipients implements Recipients
{
    /** Read the email and the name of the customer from the accounts of Identity; an order never outlives its customer. */
    public function of(CustomerId $customerId): Recipient
    {
        $account = UserModel::query()->findOrFail($customerId->value);

        return new Recipient($account->email, $account->name);
    }
}
