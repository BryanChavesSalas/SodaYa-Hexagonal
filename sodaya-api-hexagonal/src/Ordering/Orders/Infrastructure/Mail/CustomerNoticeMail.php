<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A notice to a customer, with its subject and its text already written in their language. */
final class CustomerNoticeMail extends Mailable
{
    /** Carry the subject and the text of the notice. */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {}

    /** Subject of the message. */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    /** Body of the message: the text as it is, one paragraph, without a template. */
    public function content(): Content
    {
        return new Content(htmlString: '<p>'.nl2br(e($this->body)).'</p>');
    }
}
