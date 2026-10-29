<?php

namespace Tests\Feature\Auth;

use App\Domains\Auth\Actions\SocialiteAuthAction;
use App\Domains\Auth\DTOs\GoogleAuthDTO;
use App\Domains\Auth\Exceptions\RegistrationClosedException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Registration is closed by default (personal app); existing users keep logging in, also with Google. */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.com',
        'password' => 'senha-segura-123',
        'password_confirmation' => 'senha-segura-123',
    ];

    public function test_register_is_refused_while_registration_is_closed(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->postJson('/api/v1/auth/register', $this->payload)
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.com']);
    }

    public function test_register_works_when_registration_is_enabled(): void
    {
        config(['auth.registration_enabled' => true]);

        $this->postJson('/api/v1/auth/register', $this->payload)->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'nova@example.com']);
    }

    public function test_google_does_not_create_an_account_while_registration_is_closed(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->expectException(RegistrationClosedException::class);
        try {
            app(SocialiteAuthAction::class)->execute(new GoogleAuthDTO('g-1', 'Nova Pessoa', 'nova@example.com'));
        } finally {
            $this->assertDatabaseMissing('users', ['email' => 'nova@example.com']);
        }
    }

    public function test_google_still_logs_in_existing_users_while_registration_is_closed(): void
    {
        config(['auth.registration_enabled' => false]);
        $existing = User::factory()->create(['email' => 'eu@example.com']);

        $user = app(SocialiteAuthAction::class)->execute(new GoogleAuthDTO('g-2', 'Eu', 'eu@example.com'));

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('g-2', $user->refresh()->google_id);
    }
}
