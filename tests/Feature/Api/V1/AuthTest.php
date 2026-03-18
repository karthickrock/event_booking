<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register_successfully()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+1234567890',
            'role' => 'customer'
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user',
                        'token'
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'role' => 'customer'
        ]);

        $this->assertNotNull($response->json('data.token'));
    }

    /** @test */
    public function registration_fails_with_invalid_data()
    {
        $invalidData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
            'phone' => 'invalid-phone',
            'role' => 'invalid-role'
        ];

        $response = $this->postJson('/api/v1/register', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password', 'phone', 'role']);
    }

    /** @test */
    public function registration_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+1234567890',
            'role' => 'customer'
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user',
                        'token'
                    ]
                ]);

        $this->assertNotNull($response->json('data.token'));
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        $invalidData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/v1/login', $invalidData);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    /** @test */
    public function login_fails_with_nonexistent_email()
    {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    /** @test */
    public function login_requires_email_and_password()
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);

        // Verify token is deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class
        ]);
    }

    /** @test */
    public function logout_requires_authentication()
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }
}