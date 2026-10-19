<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class StrayHttpRequestsTest extends TestCase
{
    /** A test that reaches a real external service fails: every HTTP call is faked, and the base test case enforces it. */
    public function test_a_request_to_a_service_that_is_not_faked_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Attempted request to [https://api.stripe.com/v1/payment_intents] without a matching fake.');

        Http::post('https://api.stripe.com/v1/payment_intents');
    }

    /** A faked service still answers. */
    public function test_a_faked_service_answers(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['status' => 'succeeded'])]);

        $this->assertSame('succeeded', Http::post('https://api.stripe.com/v1/payment_intents')->json('status'));
    }
}
