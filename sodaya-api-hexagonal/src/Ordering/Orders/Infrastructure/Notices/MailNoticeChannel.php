<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Notices;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Src\Ordering\Orders\Application\Contracts\NoticeChannel;
use Src\Ordering\Orders\Application\DTOs\Recipient;
use Src\Ordering\Orders\Infrastructure\Mail\CustomerNoticeMail;

final readonly class MailNoticeChannel implements NoticeChannel
{
    /** Send the notice by mail to the customer, by name, with the mailer the application is configured with. */
    public function send(Recipient $to, string $subject, string $message): void
    {
        Mail::to(new Address($to->email, $to->name))->send(new CustomerNoticeMail($subject, $message));
    }
}
