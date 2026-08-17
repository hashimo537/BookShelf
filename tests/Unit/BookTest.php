<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Bookモデルのgenres()リレーションで、紐付けたジャンルが正しく取得できる')]
    public function test_genres_relation_returns_attached_genres(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create(['name' => '技術書']);
        $book->genres()->attach($genre);

        $this->assertCount(1, $book->genres);
        $this->assertEquals('技術書', $book->genres->first()->name);
    }

    #[TestDox('Bookモデルのreviews()リレーションで、紐付けたレビューが正しく取得できる')]
    public function test_reviews_relation_returns_associated_reviews(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(2)->create(['book_id' => $book->id]);

        $this->assertCount(2, $book->reviews);
    }

    #[TestDox('averageRating()はレビューの平均値を正しく返す')]
    public function test_average_rating_returns_correct_value(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create(['book_id' => $book->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 3]);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 4]);

        // (5 + 3 + 4) / 3 = 4.0
        $this->assertEquals(4.0, $book->averageRating());
    }

    #[TestDox('averageRating()はレビューが1件も無い場合nullを返す')]
    public function test_average_rating_returns_null_when_no_reviews(): void
    {
        $book = Book::factory()->create();

        $this->assertNull($book->averageRating());
    }
}
