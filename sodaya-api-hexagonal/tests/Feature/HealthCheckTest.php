<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /** The health endpoint reports the application as up. */
    public function test_health_endpoint_reports_the_application_as_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
