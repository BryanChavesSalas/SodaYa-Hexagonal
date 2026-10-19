<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\TestCase;

final class ExpireOrdersSweepTest extends TestCase
{
    use CommitsRows;

    /** @var array<string, array{string, string}> order and soda by name of the scenario */
    private array $orders = [];

    /**
     * Two sodas with pending orders at 12:10, as if the queue had lost their jobs: two unpaid since 12:00,
     * one unpaid since 12:05, one paid at 12:00 and not confirmed, one paid at 12:05, and one confirmed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $esquina = SodaModel::factory()->create();
        $rincon = SodaModel::factory()->create();

        $this->travelTo('2026-10-05 12:00:00');
        $this->order('unpaid at noon', $esquina);
        $this->order('unpaid at noon in another soda', $rincon);
        $this->order('paid at noon', $esquina, ['payment_status' => 'approved', 'paid_at' => now()]);
        $this->order('confirmed', $esquina, ['status' => 'confirmed', 'confirmed_at' => now(), 'payment_status' => 'approved', 'paid_at' => now()]);

        $this->travelTo('2026-10-05 12:05:00');
        $this->order('unpaid at 12:05', $esquina);
        $this->order('paid at 12:05', $esquina, ['payment_status' => 'approved', 'paid_at' => now()]);

        $this->travelTo('2026-10-05 12:10:00');
    }

    /** The sweep cancels the expired orders of every soda, each with the origin of the deadline it missed. */
    public function test_sweep_cancels_the_expired_orders_of_every_soda(): void
    {
        $this->artisan('sodaya:expire-orders')
            ->expectsOutputToContain('Se cancelaron 3 pedidos vencidos.')
            ->assertSuccessful();

        $this->assertSame([
            'confirmed' => ['confirmed', null],
            'paid at 12:05' => ['pending', null],
            'paid at noon' => ['cancelled', 'confirmation_timeout'],
            'unpaid at 12:05' => ['pending', null],
            'unpaid at noon' => ['cancelled', 'payment_timeout'],
            'unpaid at noon in another soda' => ['cancelled', 'payment_timeout'],
        ], $this->statuses());
    }

    /** Running the sweep again finds nothing new and changes nothing. */
    public function test_second_sweep_has_no_effect(): void
    {
        $this->artisan('sodaya:expire-orders')->assertSuccessful();
        $before = $this->statuses();

        $this->artisan('sodaya:expire-orders')
            ->expectsOutputToContain('No había pedidos vencidos.')
            ->assertSuccessful();

        $this->assertSame($before, $this->statuses());
    }

    /** The sweep runs every minute and never twice at the same time. */
    public function test_sweep_runs_every_minute_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, 'sodaya:expire-orders'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);

        $events[0]->mutex->create($events[0]);
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(['pending', null], $this->statuses()['unpaid at noon']);
        $events[0]->mutex->forget($events[0]);
    }

    /**
     * Create a committed pending order with one line.
     *
     * @param  array<string, mixed>  $state
     */
    private function order(string $name, SodaModel $soda, array $state = []): void
    {
        $order = $this->committedFor($soda->id, function () use ($soda, $state): OrderModel {
            $order = OrderModel::factory()->create(['soda_id' => $soda->id, ...$state]);
            OrderLineModel::factory()->for($order, 'order')->create();

            return $order;
        });

        $this->orders[$name] = [$order->id, $soda->id];
    }

    /**
     * Status and cancellation origin of each order, by name.
     *
     * @return array<string, array{string, string|null}>
     */
    private function statuses(): array
    {
        $statuses = [];

        foreach ($this->orders as $name => [$orderId, $sodaId]) {
            $row = $this->committedFor($sodaId, fn (): ?object => DB::table('orders')->where('id', $orderId)->first(['status', 'cancellation_origin']));
            $statuses[$name] = [$row?->status, $row?->cancellation_origin];
        }

        ksort($statuses);

        return $statuses;
    }
}
