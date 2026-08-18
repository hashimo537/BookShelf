<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // AP01: 書籍一覧API（認証不要）
    // ---------------------------------------------------------------

    #[TestDox('書籍一覧APIはJSON形式で200を返す')]
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

    #[TestDox('書籍一覧APIのレスポンスにはジャンル情報・平均評価・レビュー件数が正しく含まれる')]
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

        $this->assertEquals(3.0, $target['reviews_avg_rating']);
        $this->assertEquals(2, $target['reviews_count']);
    }

    #[TestDox('書籍一覧APIはkeywordパラメータでタイトル検索できる')]
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

    #[TestDox('書籍一覧APIはgenre_idパラメータでジャンル絞り込みができる')]
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

    #[TestDox('書籍一覧APIは不正なsort値を指定すると422を返す')]
    public function test_book_index_rejects_invalid_sort_value(): void
    {
        $response = $this->getJson('/api/v1/books?sort=invalid-value');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort');
    }

    // ---------------------------------------------------------------
    // AP02: 書籍詳細API（認証不要）
    // ---------------------------------------------------------------

    #[TestDox('書籍詳細APIはジャンル・レビューを含むJSON形式で200を返す')]
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

    #[TestDox('書籍詳細APIは存在しないIDを指定すると404を返す')]
    public function test_book_show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    // ---------------------------------------------------------------
    // ★AP03: 書籍登録API（Sanctumトークン必須）
    // ---------------------------------------------------------------

    #[TestDox('認証済みユーザーは正しいデータで201を返し、書籍を作成できる')]
    public function test_authenticated_user_can_store_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $payload = [
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
            'user_id' => $user->id, // トークンの持ち主が自動的に登録者になる
        ]);
    }

    #[TestDox('トークンが無い状態で書籍登録APIを呼ぶと401が返る')]
    public function test_guest_cannot_store_book_without_token(): void
    {
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'テスト書籍',
            'author_name' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(401);
        $this->assertDatabaseCount('books', 0);
    }

    #[TestDox('書籍登録APIは必須項目が不足していると422を返す')]
    public function test_book_store_returns_422_when_required_field_is_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '',
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

    // ---------------------------------------------------------------
    // ★AP04: 書籍更新API（Sanctumトークン必須 + 登録者本人のみ）
    // ---------------------------------------------------------------

    #[TestDox('登録者本人は正しいデータで書籍を更新できる')]
    public function test_owner_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'title' => '更新前タイトル']);
        Sanctum::actingAs($user);
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

    #[TestDox('登録者本人でなければ書籍を更新しようとすると403が返る')]
    public function test_non_owner_cannot_update_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($otherUser);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '不正な更新',
            'author_name' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('books', ['title' => '不正な更新']);
    }

    #[TestDox('トークンが無い状態で書籍更新APIを呼ぶと401が返る')]
    public function test_guest_cannot_update_book_without_token(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '不正な更新',
            'author_name' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertStatus(401);
    }

    #[TestDox('書籍更新APIは存在しないIDを指定すると404を返す')]
    public function test_book_update_returns_404_for_nonexistent_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
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

    #[TestDox('書籍更新APIは自分自身のISBNのまま更新しても一意性エラーにならない')]
    public function test_book_update_ignores_own_isbn_for_uniqueness_check(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'isbn' => '1234567890123']);
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '更新後タイトル',
            'author_name' => $book->author,
            'isbn' => '1234567890123',
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // ★AP05: 書籍削除API（Sanctumトークン必須 + 登録者本人のみ）
    // ---------------------------------------------------------------

    #[TestDox('登録者本人は書籍を削除でき、204が返る')]
    public function test_owner_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    #[TestDox('登録者本人でなければ書籍を削除しようとすると403が返る')]
    public function test_non_owner_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    #[TestDox('トークンが無い状態で書籍削除APIを呼ぶと401が返る')]
    public function test_guest_cannot_delete_book_without_token(): void
    {
        $book = Book::factory()->create();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    #[TestDox('書籍削除APIは存在しないIDを指定すると404を返す')]
    public function test_book_destroy_returns_404_for_nonexistent_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404);
    }

    #[TestDox('書籍削除APIは関連するレビュー・お気に入り・ジャンル紐付けも連動して削除し、ジャンル自体は残す')]
    public function test_book_destroy_cascades_related_data(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->create(['book_id' => $book->id]);
        $anotherUser = User::factory()->create();
        $anotherUser->favoriteBooks()->attach($book);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertStatus(204);

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
