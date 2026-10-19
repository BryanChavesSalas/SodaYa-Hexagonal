<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Application\DTOs\Recipient;

interface NoticeChannel
{
    /** Deliver a notice to a customer through the channel: the log, the mail or whatever comes next. */
    public function send(Recipient $to, string $subject, string $message): void;
}
