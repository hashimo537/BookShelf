<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Reviewモデルのbook()リレーションで、紐付くBookが正しく取得できる')]
    public function test_book_relation_returns_associated_book(): void
    {
        $book = Book::factory()->create(['title' => 'テスト書籍']);
        $review = Review::factory()->create(['book_id' => $book->id]);

        $this->assertEquals('テスト書籍', $review->book->title);
    }

    #[TestDox('Reviewモデルのuser()リレーションで、紐付くUserが正しく取得できる')]
    public function test_user_relation_returns_associated_user(): void
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);
        $review = Review::factory()->create(['user_id' => $user->id]);

        $this->assertEquals('テスト太郎', $review->user->name);
    }
}
