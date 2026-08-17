<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('UserモデルのfavoriteBooks()リレーションで、紐付けたお気に入り書籍が正しく取得できる')]
    public function test_favorite_books_relation_returns_favorited_books(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book);

        $this->assertCount(1, $user->favoriteBooks);
        $this->assertEquals($book->id, $user->favoriteBooks->first()->id);
    }

    #[TestDox('Userモデルのreviews()リレーションで、紐付けたレビューが正しく取得できる')]
    public function test_reviews_relation_returns_users_reviews(): void
    {
        $user = User::factory()->create();
        Review::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->reviews);
    }

    #[TestDox('Userのパスワードは自動的にハッシュ化されて保存される')]
    public function test_password_is_automatically_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plain-password123',
        ]);

        $this->assertNotEquals('plain-password123', $user->password);
        $this->assertTrue(Hash::check('plain-password123', $user->password));
    }
}
