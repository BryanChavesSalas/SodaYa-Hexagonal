<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Database\Factories\OrderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Jobs\NotifyCustomerJob;
use Src\Ordering\Orders\Infrastructure\Mail\CustomerNoticeMail;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CustomerNoticesTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private UserModel $customer;

    /** Authenticate the kitchen of a soda on a Monday at noon; Marta is the customer of every order. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->actOnNewSoda(Role::Kitchen);
        $this->customer = UserModel::factory()->create(['name' => 'Marta Solís', 'email' => 'marta@sodaya.test']);
    }

    /** Marking an order ready leaves a job in the queue that knows the order and its soda, and nothing more. */
    public function test_a_ready_order_queues_a_notice_with_the_order(): void
    {
        Queue::fake();
        $order = $this->order(7);

        $this->patchJson("/api/v1/cocina/pedidos/{$order->id}/estado", ['estado' => 'listo'])->assertOk();

        Queue::assertPushed(NotifyCustomerJob::class, fn (NotifyCustomerJob $job): bool => $job->orderId === $order->id
            && $job->sodaId === $this->soda->id
            && $job->event === NotifyCustomerJob::ORDER_READY);
    }

    /** With the database queue of production, the job is in the queue once the request committed. */
    public function test_the_notice_is_queued_after_the_commit(): void
    {
        config(['queue.default' => 'database']);
        $order = $this->order(7);

        $this->patchJson("/api/v1/cocina/pedidos/{$order->id}/estado", ['estado' => 'listo'])->assertOk();

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertStringContainsString(NotifyCustomerJob::ORDER_READY, (string) DB::table('jobs')->value('payload'));
    }

    /** Running the job with the mail channel mails the customer of the order: the subject names the pickup number and the text is in Spanish. */
    public function test_the_job_mails_the_customer_with_the_mail_channel(): void
    {
        Mail::fake();
        config(['sodaya.notice_channel' => 'mail']);
        $order = $this->order(7);

        $this->runJob(new NotifyCustomerJob($order->id, $this->soda->id, NotifyCustomerJob::ORDER_READY));

        Mail::assertSent(CustomerNoticeMail::class, fn (CustomerNoticeMail $mail): bool => $mail->hasTo('marta@sodaya.test')
            && $mail->title === 'Su pedido 7 está listo'
            && $mail->body === 'Hola, Marta Solís. Su pedido con número de retiro 7 ya está listo: puede pasar a retirarlo al mostrador.');
    }

    /** The same job with the log channel writes the notice to the log and sends no mail. */
    public function test_the_job_writes_to_the_log_with_the_log_channel(): void
    {
        Mail::fake();
        config(['sodaya.notice_channel' => 'log']);
        Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Customer notice.'
            && $context['to'] === 'marta@sodaya.test'
            && $context['subject'] === 'Su pedido 7 está listo');
        $order = $this->order(7);

        $this->runJob(new NotifyCustomerJob($order->id, $this->soda->id, NotifyCustomerJob::ORDER_READY));

        Mail::assertNothingSent();
    }

    /** The cancellation notice says who cancelled and whether there is a refund, read from the order when the job runs. */
    #[DataProvider('cancellations')]
    public function test_the_cancellation_notice_says_the_origin_and_the_refund(string $state, string $origin, string $expected): void
    {
        Mail::fake();
        config(['sodaya.notice_channel' => 'mail']);
        $order = $this->order(7, OrderModel::factory()->{$state}()->cancelled($origin));

        $this->runJob(new NotifyCustomerJob($order->id, $this->soda->id, NotifyCustomerJob::ORDER_CANCELLED));

        Mail::assertSent(CustomerNoticeMail::class, fn (CustomerNoticeMail $mail): bool => $mail->hasTo('marta@sodaya.test')
            && $mail->title === 'Su pedido 7 fue cancelado'
            && str_contains($mail->body, $expected));
    }

    /**
     * Cancellations: whether the order was paid, who cancelled and what the notice must say.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function cancellations(): array
    {
        return [
            'paid, by the kitchen' => ['paid', 'kitchen', 'fue cancelado por la cocina de la soda. Se le reembolsarán los ₡5.600 que pagó.'],
            'unpaid, for lack of payment' => ['new', 'payment_timeout', 'fue cancelado porque no se recibió su pago a tiempo. No había ningún pago que devolver.'],
            'paid, for lack of confirmation' => ['paid', 'confirmation_timeout', 'fue cancelado porque la cocina no lo confirmó a tiempo. Se le reembolsarán los ₡5.600 que pagó.'],
        ];
    }

    /** The whole path: the kitchen cancels a paid order, and the queued job, once run, mails the customer about the refund. */
    public function test_kitchen_cancellation_ends_in_a_mail_about_the_refund(): void
    {
        Queue::fake();
        Mail::fake();
        config(['sodaya.notice_channel' => 'mail']);
        $order = $this->order(7);

        $this->postJson("/api/v1/cocina/pedidos/{$order->id}/cancelacion")->assertOk();
        $job = Queue::pushed(NotifyCustomerJob::class)->first();
        $this->assertInstanceOf(NotifyCustomerJob::class, $job);
        $this->runJob($job);

        Mail::assertSent(CustomerNoticeMail::class, fn (CustomerNoticeMail $mail): bool => $mail->hasTo('marta@sodaya.test')
            && str_contains($mail->body, 'fue cancelado por la cocina de la soda. Se le reembolsarán los ₡5.600 que pagó.'));
    }

    /** Run the job as a worker would: the container hands it the ports it asks for. */
    private function runJob(NotifyCustomerJob $job): void
    {
        $this->app->call([$job, 'handle']);
    }

    /** Create an order of Marta at the soda with the pickup number, confirmed unless told otherwise, with one line of two portions at ₡2800. */
    private function order(int $pickupNumber, ?OrderFactory $factory = null): OrderModel
    {
        $order = ($factory ?? OrderModel::factory()->confirmed())->create([
            'soda_id' => $this->soda->id,
            'customer_id' => $this->customer->id,
            'pickup_number' => $pickupNumber,
        ]);
        OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => 2_800, 'quantity' => 2]);

        return $order;
    }
}
