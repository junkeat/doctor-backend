<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_user_and_returns_a_working_sanctum_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Jane Doe')
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($response->json('token'))
            ->getJson('/api/consultations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_logs_in_with_valid_credentials_and_returns_a_token(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secure-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'secure-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_rejects_invalid_login_credentials(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secure-password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'incorrect-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_validates_registration_input(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Another Jane',
            'email' => 'jane@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_logs_out_and_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/consultations')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        Auth::forgetGuards();

        $this->withToken($otherToken)
            ->getJson('/api/consultations')
            ->assertOk();
    }

    public function test_rejects_logout_without_authentication(): void
    {
        $this->postJson('/api/logout')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
