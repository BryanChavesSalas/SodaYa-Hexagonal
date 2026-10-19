<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Src\Ordering\Orders\Infrastructure\Jobs\RequestRefundJob;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class JobRetriesTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private int $attempts = 0;

    /** Use the database queue of production instead of the synchronous one of the tests. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        $this->travelTo('2026-10-05 12:00:00');
    }

    /** A job that fails once goes back to the queue, waits its backoff and succeeds on the next attempt. */
    public function test_transient_failure_is_retried_after_the_backoff(): void
    {
        $this->gatewayFails(times: 1);
        RequestRefundJob::dispatch('pedido', 'soda', 5_600);

        $this->work();
        $this->assertSame(1, DB::table('jobs')->value('attempts'));
        $this->assertSame(now()->addSeconds(10)->getTimestamp(), DB::table('jobs')->value('available_at'));

        $this->travel(10)->seconds();
        $this->work();

        $this->assertSame(2, $this->attempts);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    /** A job that keeps failing is recorded in failed_jobs after its fifth attempt. */
    public function test_job_that_runs_out_of_attempts_is_recorded_as_failed(): void
    {
        $this->gatewayFails(times: PHP_INT_MAX);
        RequestRefundJob::dispatch('pedido', 'soda', 5_600);

        foreach ([10, 60, 300, 300, 0] as $backoff) {
            $this->work();
            $this->travel($backoff)->seconds();
        }

        $this->assertSame(5, $this->attempts);
        $this->assertSame(0, DB::table('jobs')->count());

        $failed = DB::table('failed_jobs')->sole();
        $this->assertStringContainsString('RequestRefundJob', $failed->payload);
        $this->assertStringContainsString('The payment gateway did not answer.', $failed->exception);
    }

    /** Make the log the job writes to fail the given number of times, as a payment gateway that does not answer would. */
    private function gatewayFails(int $times): void
    {
        Log::spy();
        Log::shouldReceive('warning')->andReturnUsing(function () use ($times): void {
            if (++$this->attempts <= $times) {
                throw new RuntimeException('The payment gateway did not answer.');
            }
        });
    }

    /** Run one job of the database queue, as a worker would. */
    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
    }
}
