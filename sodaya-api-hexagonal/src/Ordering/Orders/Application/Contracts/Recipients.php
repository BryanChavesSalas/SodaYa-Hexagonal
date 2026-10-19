<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Application\DTOs\Recipient;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;

interface Recipients
{
    /** Where and to whom the notices of a customer go: the email and the name of their account. */
    public function of(CustomerId $customerId): Recipient;
}
