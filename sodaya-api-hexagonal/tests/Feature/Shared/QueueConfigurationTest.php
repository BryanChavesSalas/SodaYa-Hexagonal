<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Tests\TestCase;

final class QueueConfigurationTest extends TestCase
{
    /** Jobs wait in the database table until a transaction that dispatched them commits. */
    public function test_database_queue_waits_for_the_commit(): void
    {
        $this->assertSame('database', config('queue.connections.database.driver'));
        $this->assertSame('jobs', config('queue.connections.database.table'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }

    /** A job that runs out of attempts is kept in failed_jobs with its own identifier. */
    public function test_failed_jobs_are_kept_in_the_database(): void
    {
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertSame('failed_jobs', config('queue.failed.table'));
    }
}
