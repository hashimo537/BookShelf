<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class RankingControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('ランキング画面は公開ページであり、ゲストでもアクセスできる')]
    public function test_ranking_is_public_and_accessible_to_guests(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
    }

    #[TestDox('評価の高い順に書籍が正しく並んで表示される')]
    public function test_books_are_ordered_by_average_rating_descending(): void
    {
        $lowRatedBook = Book::factory()->create(['title' => '低評価の本']);
        Review::factory()->create(['book_id' => $lowRatedBook->id, 'rating' => 2]);

        $highRatedBook = Book::factory()->create(['title' => '高評価の本']);
        Review::factory()->create(['book_id' => $highRatedBook->id, 'rating' => 5]);

        $midRatedBook = Book::factory()->create(['title' => '中評価の本']);
        Review::factory()->create(['book_id' => $midRatedBook->id, 'rating' => 3]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($highRatedBook, $midRatedBook, $lowRatedBook) {
            $ids = $rankedBooks->pluck('id')->toArray();

            return $ids === [$highRatedBook->id, $midRatedBook->id, $lowRatedBook->id];
        });
    }

    #[TestDox('レビュー0件の書籍はランキングから除外される')]
    public function test_books_with_no_reviews_are_excluded_from_ranking(): void
    {
        $reviewedBook = Book::factory()->create(['title' => 'レビューがある本']);
        Review::factory()->create(['book_id' => $reviewedBook->id, 'rating' => 4]);

        $unreviewedBook = Book::factory()->create(['title' => 'レビューが無い本']);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertSee('レビューがある本');
        $response->assertDontSee('レビューが無い本');
    }

    #[TestDox('レビューが1件も無い場合はランキングが空になる')]
    public function test_ranking_shows_message_when_no_books_have_reviews(): void
    {
        Book::factory()->count(3)->create(); // レビュー無しの書籍のみ

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas('rankedBooks', fn ($rankedBooks) => $rankedBooks->isEmpty());
    }

    #[TestDox('ランキングの表示件数はTOP10までに制限される')]
    public function test_ranking_is_limited_to_top_ten(): void
    {
        // 11冊、それぞれ異なる評価のレビューを1件ずつ付けて11冊すべてが
        // ランキング対象になり得る状態を作る
        foreach (range(1, 11) as $i) {
            $book = Book::factory()->create();
            Review::factory()->create([
                'book_id' => $book->id,
                'rating' => ($i % 5) + 1, // 1〜5の範囲でばらけさせる
            ]);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas('rankedBooks', fn ($rankedBooks) => $rankedBooks->count() === 10);
    }
}
