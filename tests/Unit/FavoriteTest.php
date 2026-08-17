<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * favoritesテーブルは複合主キー（user_id, book_id）のみの純粋な中間テーブルで、
     * 専用のEloquentモデルを持たない設計のため、
     * User::favoriteBooks() リレーション経由でuser_id・book_idの紐付けを検証する。
     */
    #[TestDox('favoritesテーブルにuser_idとbook_idが正しく保存される')]
    public function test_user_id_and_book_id_are_stored_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->favoriteBooks()->attach($book);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    #[TestDox('User::favoriteBooks()で紐付けたBookが正しく取得できる')]
    public function test_favorite_books_relation_returns_attached_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => 'お気に入りの本']);

        $user->favoriteBooks()->attach($book);

        $this->assertCount(1, $user->favoriteBooks);
        $this->assertEquals('お気に入りの本', $user->favoriteBooks->first()->title);
    }
}
