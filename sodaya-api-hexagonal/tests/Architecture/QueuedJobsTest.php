<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class QueuedJobsTest extends TestCase
{
    /** Every job of the product goes to the queue, retries a transient failure and waits longer each time. */
    #[DataProvider('jobs')]
    public function test_job_is_queued_with_retries_and_backoff(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertTrue($reflection->implementsInterface(ShouldQueue::class), "{$class} does not implement ShouldQueue.");
        $this->assertGreaterThan(1, $defaults['tries'] ?? 0, "{$class} does not retry.");
        $this->assertNotEmpty($defaults['backoff'] ?? [], "{$class} retries without waiting.");
    }

    /**
     * Every class under an Infrastructure/Jobs folder of src.
     *
     * @return array<string, array{class-string}>
     */
    public static function jobs(): array
    {
        $jobs = [];

        foreach (glob(__DIR__.'/../../src/*/*/Infrastructure/Jobs/*.php') ?: [] as $file) {
            $relative = substr((string) realpath($file), strlen((string) realpath(__DIR__.'/../../src')) + 1, -4);
            /** @var class-string $class */
            $class = 'Src\\'.str_replace('/', '\\', $relative);
            $jobs[basename($relative)] = [$class];
        }

        return $jobs;
    }
}
