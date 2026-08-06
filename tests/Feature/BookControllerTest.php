<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 公開ページ（ゲスト可）
    // ---------------------------------------------------------------

    public function test_guest_can_view_top_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_guest_can_view_book_index(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertViewIs('books.index');
    }

    public function test_guest_can_view_book_show(): void
    {
        $book = Book::factory()->create();

        $response = $this->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewIs('books.show');
        $response->assertSee($book->title);
    }

    // ---------------------------------------------------------------
    // 認証必須（登録画面）
    // ---------------------------------------------------------------

    public function test_guest_cannot_view_create_page(): void
    {
        $response = $this->get(route('books.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('books.create'));

        $response->assertOk();
        $response->assertViewIs('books.create');
    }

    // ---------------------------------------------------------------
    // 登録（store）
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_store_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'テスト駆動開発',
            'author' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'description' => 'テスト用の説明文です。',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $this->assertDatabaseHas('books', [
            'title' => 'テスト駆動開発',
            'author_name' => 'テスト太郎', // フォームは'author'だがDBカラムは'author_name'
            'isbn' => '1234567890123',
            'user_id' => $user->id,
        ]);

        $book = Book::where('isbn', '1234567890123')->firstOrFail();
        $this->assertTrue($book->genres->contains($genre));

        $response->assertRedirect(route('books.show', $book));
    }

    public function test_guest_cannot_store_book(): void
    {
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'ゲスト投稿',
            'author' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->post(route('books.store'), $payload);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('books', 0);
    }

    // ---------------------------------------------------------------
    // バリデーション（StoreBookRequest）
    // ---------------------------------------------------------------

    public function test_store_fails_when_title_is_missing(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '',
            'author' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_isbn_is_not_13_digits(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'テスト書籍',
            'author' => 'テスト太郎',
            'isbn' => '123', // 桁数不正
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasErrors('isbn');
    }

    public function test_store_fails_when_isbn_already_exists(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $existing = Book::factory()->create(['isbn' => '1234567890123']);

        $payload = [
            'title' => 'テスト書籍',
            'author' => 'テスト太郎',
            'isbn' => '1234567890123', // 既存と重複
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasErrors('isbn');
    }

    public function test_store_fails_when_no_genre_selected(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'テスト書籍',
            'author' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => '2020-01-01',
            'genres' => [],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasErrors('genres');
    }

    public function test_store_fails_when_published_date_is_future(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'テスト書籍',
            'author' => 'テスト太郎',
            'isbn' => '1234567890123',
            'published_date' => now()->addDay()->format('Y-m-d'), // 未来日
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasErrors('published_date');
    }

    // ---------------------------------------------------------------
    // 編集画面（認証＋認可）
    // ---------------------------------------------------------------

    public function test_owner_can_view_edit_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('books.edit', $book));

        $response->assertOk();
        $response->assertViewIs('books.edit');
    }

    public function test_non_owner_cannot_view_edit_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get(route('books.edit', $book));

        $response->assertForbidden(); // 403
    }

    // ---------------------------------------------------------------
    // 更新（update）
    // ---------------------------------------------------------------

    public function test_owner_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '更新後のタイトル',
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->put(route('books.update', $book), $payload);

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後のタイトル',
        ]);
    }

    public function test_non_owner_cannot_update_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '不正な更新',
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date->format('Y-m-d'),
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($otherUser)->put(route('books.update', $book), $payload);

        $response->assertForbidden();
        $this->assertDatabaseMissing('books', ['title' => '不正な更新']);
    }

    // ---------------------------------------------------------------
    // 削除（destroy）
    // ---------------------------------------------------------------

    public function test_owner_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_non_owner_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        $response->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_non_existing_book_returns_404(): void
    {
        $response = $this->get('/books/999999');

        $response->assertNotFound();
    }
}