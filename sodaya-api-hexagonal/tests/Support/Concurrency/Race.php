<?php

declare(strict_types=1);

namespace Tests\Support\Concurrency;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Starts several PHP processes that wait behind the same gate, opens the gate once all of them are waiting,
 * and collects what each one printed. The gate is a PostgreSQL advisory lock held by the test.
 */
final class Race
{
    public const int GATE = 54_000;

    private const int TIMEOUT_SECONDS = 10;

    /**
     * Run a script in one process per list of arguments; the script receives the gate as its first argument.
     *
     * @param  list<list<string>>  $arguments
     * @return list<string>
     */
    public static function run(string $script, array $arguments): array
    {
        $gate = DB::connection('pgsql_admin');
        $gate->select('select pg_advisory_lock(?)', [self::GATE]);

        $processes = [];

        foreach ($arguments as $args) {
            $process = proc_open([PHP_BINARY, $script, (string) self::GATE, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            Assert::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        try {
            self::waitUntilAllWait(count($arguments));
        } finally {
            $gate->select('select pg_advisory_unlock(?)', [self::GATE]);
        }

        $outputs = [];

        foreach ($processes as [$process, $pipes]) {
            $output = trim((string) stream_get_contents($pipes[1]));
            $errors = trim((string) stream_get_contents($pipes[2]));
            proc_close($process);
            Assert::assertSame('', $errors, "A process of the race failed: {$errors}");
            $outputs[] = $output;
        }

        return $outputs;
    }

    /** Poll PostgreSQL until every process is blocked on the gate. */
    private static function waitUntilAllWait(int $count): void
    {
        $deadline = microtime(true) + self::TIMEOUT_SECONDS;

        do {
            $waiting = (int) DB::connection('pgsql_admin')->scalar(
                "select count(*) from pg_locks where locktype = 'advisory' and objid = ? and not granted",
                [self::GATE],
            );

            if ($waiting === $count) {
                return;
            }

            usleep(20_000);
        } while (microtime(true) < $deadline);

        Assert::fail("Only {$waiting} of {$count} processes reached the gate.");
    }
}
