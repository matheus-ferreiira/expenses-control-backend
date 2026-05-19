<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_api_health_returns_successful_response(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        // Unauthenticated requests return 401, meaning the API is reachable
        $response->assertStatus(401);
    }
}
