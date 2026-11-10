<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_application_reports_healthy(): void
    {
        $this->get('/up')->assertOk();
    }
}
