<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** Fail any test that reaches a real HTTP service: every external call is faked. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
