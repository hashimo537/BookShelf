<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 会員登録（Fortify: Features::registration()）
    // ---------------------------------------------------------------

    #[TestDox('ゲストは会員登録画面を表示できる')]
    public function test_guest_can_view_register_page(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    #[TestDox('ログイン済みユーザーが会員登録画面にアクセスするとHOMEへリダイレクトされる')]
    public function test_authenticated_user_is_redirected_away_from_register_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        // Fortifyのguestミドルウェアにより、ログイン済みなら登録画面にはアクセスできない
        
        $response->assertRedirect(route('home'));
    }

    #[TestDox('全項目正しい情報を入力すると会員登録が成功し、パスワードがハッシュ化されて保存される')]
    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => 'hanako@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => '山田花子',
            'email' => 'hanako@example.com',
        ]);

        $user = User::where('email', 'hanako@example.com')->firstOrFail();

        // パスワードが平文のまま保存されていないことを確認
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    #[TestDox('名前が未入力の場合は会員登録に失敗する')]
    public function test_registration_fails_when_name_is_missing(): void
    {
        $response = $this->post(route('register'), [
            'name' => '',
            'email' => 'hanako@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name' => '名前は必須です。']);
        $this->assertDatabaseCount('users', 0);
    }

    #[TestDox('名前が255文字を超える場合は会員登録に失敗する')]
    public function test_registration_fails_when_name_exceeds_max_length(): void
    {
        $response = $this->post(route('register'), [
            'name' => str_repeat('あ', 256),
            'email' => 'hanako@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name' => '名前は255文字以内で入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }


    #[TestDox('メールアドレスが未入力の場合は会員登録に失敗する')]
    public function test_registration_fails_when_email_is_missing(): void
    {
        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスは必須です。']);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    #[TestDox('メールアドレスが255文字を超える場合は会員登録に失敗する')]
    public function test_registration_fails_when_email_exceeds_max_length(): void
    {
        // ローカル部を長くして255文字を超えるメールアドレスにする
        $longLocalPart = str_repeat('a', 250);

        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => "{$longLocalPart}@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスは255文字以内で入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }

    #[TestDox('パスワードが未入力の場合は会員登録に失敗する')]
    public function test_registration_fails_when_password_is_missing(): void
    {
        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => 'hanako@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードは必須です。']);
        $this->assertDatabaseCount('users', 0);
    }

    #[TestDox('パスワードが7文字以下の場合は会員登録に失敗する')]
    public function test_registration_fails_when_password_is_seven_characters_or_less(): void
    {
        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => 'hanako@example.com',
            'password' => '1234567', // 7文字
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }



    #[TestDox('既に登録済みのメールアドレスでは会員登録に失敗する')]
    public function test_registration_fails_when_email_already_taken(): void
    {
        User::factory()->create(['email' => 'hanako@example.com']);

        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => 'hanako@example.com', // 既存と重複
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'このメールアドレスは既に使用されております。']);
        $this->assertDatabaseCount('users', 1); // 新規追加はされていない
    }

    #[TestDox('確認用パスワードが一致しない場合は会員登録に失敗する')]
    public function test_registration_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->post(route('register'), [
            'name' => '山田花子',
            'email' => 'hanako@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません。']);
        $this->assertDatabaseCount('users', 0);
    }

    // ---------------------------------------------------------------
    // ログイン
    // ---------------------------------------------------------------

    #[TestDox('ゲストはログイン画面を表示できる')]
    public function test_guest_can_view_login_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    #[TestDox('ログイン済みユーザーがログイン画面にアクセスすると別ページへリダイレクトされる')]
    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect();
        $response->assertStatus(302);
    }

    #[TestDox('正しいメールアドレスとパスワードでログインできる')]
    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'taro@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    #[TestDox('パスワードが間違っているとログインできない')]
    public function test_user_cannot_login_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
    }

    #[TestDox('登録されていないメールアドレスではログインできない')]
    public function test_user_cannot_login_with_unregistered_email(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'not-registered@example.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
    }

    // ---------------------------------------------------------------
    // ログアウト
    // ---------------------------------------------------------------

    #[TestDox('ログイン済みユーザーはログアウトできる')]
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }

    #[TestDox('未ログイン状態でログアウトを実行するとログイン画面へリダイレクトされる')]
    public function test_guest_cannot_logout(): void
    {
        $response = $this->post(route('logout'));

        // 未ログインの状態でログアウトを叩いても、ログイン画面へ誘導される
        $response->assertRedirect(route('login'));
    }
}
