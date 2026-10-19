<?php

declare(strict_types=1);

/*
 * Boot the application in a process of a race and wait at the gate the test holds.
 * Usage from a script: [$gate, ...$arguments] = require __DIR__.'/boot.php';
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$arguments = array_slice($_SERVER['argv'], 1);

DB::select('select pg_advisory_lock_shared(?)', [(int) $arguments[0]]);

return $arguments;
