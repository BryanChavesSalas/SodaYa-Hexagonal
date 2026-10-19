<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\DB;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Billing\Payments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentPaymentRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private EloquentPaymentRepository $repository;

    private OrderModel $order;

    private SodaId $soda;

    /** A pending order of a customer at a soda, with the transaction scoped to that soda. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentPaymentRepository;
        $this->order = OrderModel::factory()->create();
        $this->soda = new SodaId($this->order->soda_id);
        $this->scopeQueriesTo($this->order->soda_id);
    }

    /** A new attempt is stored pending, with its provider, its amount and its key, and answered as it is. */
    public function test_open_stores_a_pending_attempt_and_answers_it(): void
    {
        $attempt = $this->attempt();

        $opened = $this->repository->open($attempt);

        $this->assertSame($attempt->id->value, $opened->id->value);
        $this->assertDatabaseHas('payments', [
            'id' => $attempt->id->value,
            'order_id' => $this->order->id,
            'soda_id' => $this->order->soda_id,
            'customer_id' => $this->order->customer_id,
            'provider' => 'fake',
            'amount' => 5_600,
            'status' => 'pending',
            'reference' => null,
            'idempotency_key' => 'pago-'.$attempt->id->value,
        ]);
        $this->assertEquals($attempt, $this->repository->latestOf($attempt->orderId, $this->soda));
    }

    /** While the order has a pending or approved payment, opening another answers the one that stands and stores nothing. */
    public function test_open_answers_the_live_payment_instead_of_a_second_attempt(): void
    {
        $first = $this->repository->open($this->attempt());

        $this->assertSame($first->id->value, $this->repository->open($this->attempt())->id->value);

        $first->settle(new ChargeResult(PaymentStatus::Approved, new ChargeReference('pi_3SodaYa')), Payments::at('12:01'));
        $this->repository->save($first);

        $standing = $this->repository->open($this->attempt());

        $this->assertSame($first->id->value, $standing->id->value);
        $this->assertTrue($standing->isApproved());
        $this->assertSame(1, DB::table('payments')->count());
    }

    /** After a rejected payment the next attempt is a new row: the index only covers the live ones. */
    public function test_open_stores_a_new_attempt_after_a_rejected_one(): void
    {
        $first = $this->repository->open($this->attempt());
        $first->settle(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_declined'), 'generic_decline'), Payments::at('12:01'));
        $this->repository->save($first);

        $second = $this->repository->open($this->attempt());

        $this->assertNotSame($first->id->value, $second->id->value);
        $this->assertSame(2, DB::table('payments')->count());
    }

    /** Saving persists the outcome: the status, the reference, the code of the bank and the moment. */
    public function test_save_persists_the_outcome(): void
    {
        $payment = $this->repository->open($this->attempt());
        $payment->settle(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_declined'), 'insufficient_funds'), Payments::at('12:01'));

        $this->repository->save($payment);

        $stored = $this->repository->findForUpdate($payment->id, $this->soda);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isRejected());
        $this->assertSame('pi_declined', $stored->reference?->value);
        $this->assertSame('insufficient_funds', $stored->declineCode);
        $this->assertEquals(Payments::at('12:01'), $stored->settledAt);
        $this->assertNotNull(DB::table('payments')->value('updated_at'));
    }

    /** The latest attempt is the newest one; an order without attempts has none. */
    public function test_latest_of_answers_the_newest_attempt(): void
    {
        $this->assertNull($this->repository->latestOf(new OrderId($this->order->id), $this->soda));

        $first = $this->repository->open($this->attempt('12:00'));
        $first->settle(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_declined'), 'generic_decline'), Payments::at('12:01'));
        $this->repository->save($first);
        $second = $this->repository->open($this->attempt('12:02'));

        $this->assertSame($second->id->value, $this->repository->latestOf($second->orderId, $this->soda)?->id->value);
    }

    /** The payments of a soda are not found from another soda: the filter and the policy both hide them. */
    public function test_payments_of_another_soda_are_not_found(): void
    {
        $payment = $this->repository->open($this->attempt());
        $otherSoda = new SodaId(OrderModel::factory()->create()->soda_id);
        $this->scopeQueriesTo($otherSoda->value);

        $this->assertNull($this->repository->findForUpdate($payment->id, $otherSoda));
        $this->assertNull($this->repository->latestOf($payment->orderId, $otherSoda));
    }

    /** A new attempt of ₡5600 to pay the order of the scenario with the fake provider. */
    private function attempt(string $time = '12:00'): Payment
    {
        return Payment::open(
            $this->repository->nextId(),
            new OrderId($this->order->id),
            $this->soda,
            new CustomerId($this->order->customer_id),
            'fake',
            new Amount(5_600),
            Payments::at($time),
        );
    }
}
