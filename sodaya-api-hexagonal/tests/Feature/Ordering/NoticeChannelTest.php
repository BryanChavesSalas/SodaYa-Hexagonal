<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Ordering\Orders\Application\Contracts\NoticeChannel;
use Src\Ordering\Orders\Application\DTOs\Recipient;
use Src\Ordering\Orders\Infrastructure\Mail\CustomerNoticeMail;
use Src\Ordering\Orders\Infrastructure\Notices\LogNoticeChannel;
use Src\Ordering\Orders\Infrastructure\Notices\MailNoticeChannel;
use Tests\TestCase;
use UnhandledMatchError;

final class NoticeChannelTest extends TestCase
{
    /** Changing the configuration changes the class the container hands out, and nothing else. */
    public function test_the_channel_comes_from_configuration(): void
    {
        config(['sodaya.notice_channel' => 'mail']);
        $this->assertInstanceOf(MailNoticeChannel::class, app(NoticeChannel::class));

        config(['sodaya.notice_channel' => 'log']);
        $this->assertInstanceOf(LogNoticeChannel::class, app(NoticeChannel::class));
    }

    /** A channel nobody wrote fails as soon as something asks for it, instead of notifying nobody in silence. */
    public function test_unknown_channel_fails_loudly(): void
    {
        config(['sodaya.notice_channel' => 'paloma']);

        $this->expectException(UnhandledMatchError::class);
        $this->expectExceptionMessage('paloma');

        app(NoticeChannel::class);
    }

    /** The mail channel sends one message to the recipient, with the subject and the text it was given. */
    public function test_mail_channel_sends_the_notice_to_the_recipient(): void
    {
        Mail::fake();

        new MailNoticeChannel()->send(new Recipient('marta@sodaya.test', 'Marta Solís'), 'Su pedido 7 está listo', 'Hola, Marta Solís.');

        Mail::assertSent(CustomerNoticeMail::class, fn (CustomerNoticeMail $mail): bool => $mail->hasTo('marta@sodaya.test', 'Marta Solís')
            && $mail->title === 'Su pedido 7 está listo'
            && $mail->body === 'Hola, Marta Solís.');
        Mail::assertSentCount(1);
    }

    /** The log channel writes the notice with its recipient, its subject and its text. */
    public function test_log_channel_writes_the_notice_to_the_log(): void
    {
        Log::shouldReceive('info')->once()->with('Customer notice.', [
            'to' => 'marta@sodaya.test', 'subject' => 'Su pedido 7 está listo', 'message' => 'Hola, Marta Solís.',
        ]);

        new LogNoticeChannel()->send(new Recipient('marta@sodaya.test', 'Marta Solís'), 'Su pedido 7 está listo', 'Hola, Marta Solís.');
    }

    /** The mail carries the subject and renders the text, one line per line, without a template. */
    public function test_mail_renders_the_subject_and_the_text(): void
    {
        $mail = new CustomerNoticeMail('Su pedido 7 está listo', "Hola, Marta Solís.\nPase al mostrador.");

        $mail->assertHasSubject('Su pedido 7 está listo');
        $mail->assertSeeInHtml('Hola, Marta Solís.');
        $mail->assertSeeInHtml('Pase al mostrador.');
        $mail->assertSeeInHtml('<br />', escape: false);
    }
}
