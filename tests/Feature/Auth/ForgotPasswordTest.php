<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_200_for_nonexistent_email_to_prevent_enumeration(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'notregistered@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_returns_same_status_for_existing_and_nonexistent_email(): void
    {
        User::factory()->create(['email' => 'registered@example.com']);

        $existing = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'registered@example.com',
        ]);

        $nonExistent = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'notregistered@example.com',
        ]);

        $existing->assertStatus(200);
        $nonExistent->assertStatus(200);
        $this->assertEquals($existing->json('success'), $nonExistent->json('success'));
    }

    public function test_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }
}
