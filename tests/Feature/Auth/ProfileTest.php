<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_name(): void
    {
        $user = User::factory()->create(['name' => 'Nome Antigo']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/profile', ['name' => 'Nome Novo'])
            ->assertOk();

        $this->assertSame('Nome Novo', $response->json('data.name'));
        $this->assertSame('Nome Novo', $user->fresh()->name);
    }

    public function test_empty_name_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/profile', ['name' => ''])
            ->assertUnprocessable();
    }

    public function test_user_can_change_password_with_current_one(): void
    {
        $user = User::factory()->create(['password' => bcrypt('senha-antiga-123')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'senha-antiga-123',
                'password' => 'senha-nova-12345',
                'password_confirmation' => 'senha-nova-12345',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('senha-nova-12345', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('senha-antiga-123')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'senha-errada',
                'password' => 'senha-nova-12345',
                'password_confirmation' => 'senha-nova-12345',
            ])
            ->assertUnprocessable();

        $this->assertTrue(Hash::check('senha-antiga-123', $user->fresh()->password));
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('senha-antiga-123')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'senha-antiga-123',
                'password' => 'senha-nova-12345',
                'password_confirmation' => 'outra-coisa',
            ])
            ->assertUnprocessable();
    }
}
