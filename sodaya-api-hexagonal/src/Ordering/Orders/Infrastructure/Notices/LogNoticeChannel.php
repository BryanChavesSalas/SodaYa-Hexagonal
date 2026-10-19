<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Notices;

use Illuminate\Support\Facades\Log;
use Src\Ordering\Orders\Application\Contracts\NoticeChannel;
use Src\Ordering\Orders\Application\DTOs\Recipient;

final readonly class LogNoticeChannel implements NoticeChannel
{
    /** Leave the notice in the log: the channel of development and of the tests. */
    public function send(Recipient $to, string $subject, string $message): void
    {
        Log::info('Customer notice.', ['to' => $to->email, 'subject' => $subject, 'message' => $message]);
    }
}
