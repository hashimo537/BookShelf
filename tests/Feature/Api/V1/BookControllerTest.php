<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // AP01: 書籍一覧API
    // ---------------------------------------------------------------

    public function test_book_index_returns_json_with_200(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'author', 'isbn', 'published_date', 'genres'],
            ],
        ]);
    }

    public function test_book_index_includes_genres_average_rating_and_review_count(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create(['name' => '技術書']);
        $book->genres()->attach($genre);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 4]);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 2]);

        $response = $this->getJson('/api/v1/books');

        $response->assertOk();
        $response->assertJsonFragment(['name' => '技術書']);

        $data = collect($response->json('data'));
        $target = $data->firstWhere('id', $book->id);

        $this->assertEquals(3.0, $target['reviews_avg_rating']); // (4+2)/2
        $this->assertEquals(2, $target['reviews_count']);
    }

    public function test_book_index_filters_by_keyword(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);
        Book::factory()->create(['title' => '人を動かす']);

        $response = $this->getJson('/api/v1/books?keyword=猫');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('吾輩は猫である'));
        $this->assertFalse($titles->contains('人を動かす'));
    }

    public function test_book_index_filters_by_genre_id(): void
    {
        $targetGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();

        $matchingBook = Book::factory()->create();
        $matchingBook->genres()->attach($targetGenre);

        $unrelatedBook = Book::factory()->create();
        $unrelatedBook->genres()->attach($otherGenre);

        $response = $this->getJson("/api/v1/books?genre_id={$targetGenre->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($matchingBook->id));
        $this->assertFalse($ids->contains($unrelatedBook->id));
    }

    public function test_book_index_rejects_invalid_sort_value(): void
    {
        $response = $this->getJson('/api/v1/books?sort=invalid-value');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort');
    }

    // ---------------------------------------------------------------
    // AP02: 書籍詳細API
    // ---------------------------------------------------------------

    public function test_book_show_returns_json_with_200(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->create(['book_id' => $book->id]);

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $book->id);
        $response->assertJsonPath('data.title', $book->title);
        $response->assertJsonStructure([
            'data' => [
                'genres',
                'reviews' => [
                    '*' => ['id', 'user_name', 'rating', 'comment', 'created_at'],
                ],
            ],
        ]);
        $response->assertJsonFragment(['id' => $review->id]);
    }

    public function test_book_show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    // ---------------------------------------------------------------
    // AP03: 書籍登録API
    // ---------------------------------------------------------------

    public function test_book_store_creates_book_with_valid_data(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'title' => 'APIから登録した本',
            'author_name' => 'API太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'description' => 'API経由での登録テスト',
            'genres' => [$genre->id],
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'APIから登録した本');

        $this->assertDatabaseHas('books', [
            'title' => 'APIから登録した本',
            'author_name' => 'API太郎',
            'isbn' => '1234567890123',
            'user_id' => $user->id,
        ]);
    }

    public function test_book_store_returns_422_when_required_field_is_missing(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'title' => '', // タイトル未入力
            'author_name' => 'API太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_store_returns_422_when_user_id_does_not_exist(): void
    {
        $genre = Genre::factory()->create();

        $payload = [
            'user_id' => 99999, // 存在しないユーザーID
            'title' => 'テスト書籍',
            'author_name' => 'API太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('user_id');
    }

    // ---------------------------------------------------------------
    // AP04: 書籍更新API
    // ---------------------------------------------------------------

    public function test_book_update_updates_book_with_valid_data(): void
    {
        $book = Book::factory()->create(['title' => '更新前タイトル']);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '更新後タイトル',
            'author_name' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertOk();
        $response->assertJsonPath('data.title', '更新後タイトル');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後タイトル',
        ]);
    }

    public function test_book_update_returns_404_for_nonexistent_id(): void
    {
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'テスト',
            'author_name' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->putJson('/api/v1/books/99999', $payload);

        $response->assertStatus(404);
    }

    public function test_book_update_ignores_own_isbn_for_uniqueness_check(): void
    {
        // 自分自身のISBNのまま更新しても一意性エラーにならないことを確認
        $book = Book::factory()->create(['isbn' => '1234567890123']);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '更新後タイトル',
            'author_name' => $book->author,
            'isbn' => '1234567890123', // 自分自身と同じ
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // AP05: 書籍削除API
    // ---------------------------------------------------------------

    public function test_book_destroy_deletes_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_book_destroy_returns_404_for_nonexistent_id(): void
    {
        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404);
    }

    public function test_book_destroy_cascades_related_data(): void
    {
        // 関連データ（レビュー・お気に入り・ジャンル紐付け）が
        // cascadeOnDelete により一緒に削除されることを確認
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->create(['book_id' => $book->id]);
        $user->favoriteBooks()->attach($book);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertStatus(204);

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);

        // ジャンル自体は削除されない（restrictOnDeleteの対象はgenre_id側なので影響なし）
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
