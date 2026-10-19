<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_success_without_configuration_details(): void
    {
        $response = $this->get('/up');

        $response->assertOk()
            ->assertDontSee('APP_KEY')
            ->assertDontSee('DB_PASSWORD');
    }
}
