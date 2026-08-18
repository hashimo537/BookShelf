<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('正しいメールアドレス・パスワードでログインするとトークンが発行される')]
    public function test_login_returns_token_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'taro@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'token_type']);
        $response->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    #[TestDox('パスワードが間違っている場合はログインに失敗する')]
    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    #[TestDox('登録されていないメールアドレスではログインに失敗する')]
    public function test_login_fails_with_unregistered_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'not-registered@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    #[TestDox('メールアドレスまたはパスワードが未入力の場合は422を返す')]
    public function test_login_fails_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    #[TestDox('認証済みユーザーはログアウトでき、トークンが無効化される')]
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(204);
    }

    #[TestDox('未認証の状態ではログアウトAPIを実行すると401が返る')]
    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }
}
