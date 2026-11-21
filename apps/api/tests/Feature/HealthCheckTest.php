<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_api_v1_health_returns_ok_with_database_and_redis(): void
    {
        Redis::shouldReceive('ping')->andReturn(true);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'services' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                ],
                'version' => '1.1.0',
            ]);
    }

    public function test_p2_sec_info_01_health_masks_exception_details_when_service_fails(): void
    {
        Redis::shouldReceive('ping')->andThrow(new \RuntimeException('Sensitive DB connection failed: host=10.0.0.5 password=secret'));

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'degraded',
                'services' => [
                    'database' => 'ok',
                    'redis' => 'unreachable',
                ],
            ]);

        // Ensure internal credentials / exception message are not leaked
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('10.0.0.5', $response->getContent());
    }
}
