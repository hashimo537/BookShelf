<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 公開ページ（ゲスト可）
    // ---------------------------------------------------------------

    #[TestDox('ゲストでも書籍一覧を閲覧できる')]
    public function test_guest_can_view_book_index(): void
    {
        $this->withoutExceptionHandling(); // ← 一時的に追加
        Book::factory()->count(3)->create();

        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertViewIs('books.index');
    }

    #[TestDox('ゲストでも書籍詳細を閲覧できる')]
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

    #[TestDox('未ログイン状態では書籍登録画面にアクセスできない')]
    public function test_guest_cannot_view_create_page(): void
    {
        $response = $this->get(route('books.create'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーは書籍登録画面を表示できる')]
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

    #[TestDox('ログイン済みユーザーは書籍を登録できる')]
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

    #[TestDox('未ログイン状態では書籍を登録できない')]
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

    #[TestDox('タイトルが未入力の場合は登録に失敗する')]
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

    #[TestDox('ISBNが13桁でない場合は登録に失敗する')]
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

    #[TestDox('ISBNが既存の書籍と重複する場合は登録に失敗する')]
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

    #[TestDox('ジャンルが1つも選択されていない場合は登録に失敗する')]
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

    #[TestDox('出版日が未来日の場合は登録に失敗する')]
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

    #[TestDox('登録者本人は書籍編集画面を表示できる')]
    public function test_owner_can_view_edit_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('books.edit', $book));

        $response->assertOk();
        $response->assertViewIs('books.edit');
    }

    #[TestDox('登録者本人でなければ書籍編集画面にアクセスすると403になる')]
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

    #[TestDox('登録者本人は書籍を更新できる')]
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

    #[TestDox('登録者本人でなければ書籍を更新しようとすると403になる')]
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

    #[TestDox('登録者本人は書籍を削除できる')]
    public function test_owner_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    #[TestDox('登録者本人でなければ書籍を削除しようとすると403になる')]
    public function test_non_owner_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        $response->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
    // ---------------------------------------------------------------
    // ★検索・絞り込み・ソート（books.index）
    // ---------------------------------------------------------------

    #[TestDox('keywordパラメータでタイトル・著者を検索できる')]
    public function test_index_filters_by_keyword(): void
    {
        Book::factory()->create(['title' => '吾輩は猫である']);
        Book::factory()->create(['title' => '人を動かす']);

        $response = $this->get(route('books.index', ['keyword' => '猫']));

        $response->assertOk();
        $response->assertSee('吾輩は猫である');
        $response->assertDontSee('人を動かす');
    }

    #[TestDox('genreパラメータでジャンル絞り込みができる')]
    public function test_index_filters_by_genre(): void
    {
        $targetGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();

        $matchingBook = Book::factory()->create(['title' => '対象ジャンルの本']);
        $matchingBook->genres()->attach($targetGenre);

        $unrelatedBook = Book::factory()->create(['title' => '無関係な本']);
        $unrelatedBook->genres()->attach($otherGenre);

        $response = $this->get(route('books.index', ['genre' => $targetGenre->id]));

        $response->assertOk();
        $response->assertSee('対象ジャンルの本');
        $response->assertDontSee('無関係な本');
    }

    #[TestDox('sort=ratingで評価の高い順に並ぶ')]
    public function test_index_sorts_by_rating_descending(): void
    {
        $lowRated = Book::factory()->create();
        Review::factory()->create(['book_id' => $lowRated->id, 'rating' => 2]);

        $highRated = Book::factory()->create();
        Review::factory()->create(['book_id' => $highRated->id, 'rating' => 5]);

        $response = $this->get(route('books.index', ['sort' => 'rating']));

        $response->assertOk();
        $response->assertViewHas('books', function ($books) use ($highRated, $lowRated) {
            $ids = collect($books->items())->pluck('id')->toArray();

            return array_search($highRated->id, $ids) < array_search($lowRated->id, $ids);
        });
    }

    #[TestDox('sort=titleでタイトルの昇順に並ぶ')]
    public function test_index_sorts_by_title(): void
    {
        Book::factory()->create(['title' => 'ぜんぶの本']);
        Book::factory()->create(['title' => 'あいうえおの本']);

        $response = $this->get(route('books.index', ['sort' => 'title']));

        $response->assertOk();
        $response->assertViewHas('books', function ($books) {
            $titles = collect($books->items())->pluck('title')->toArray();

            return array_search('あいうえおの本', $titles) < array_search('ぜんぶの本', $titles);
        });
    }

    #[TestDox('不正なsort値を指定するとバリデーションエラーになる')]
    public function test_index_rejects_invalid_sort_value(): void
    {
        $response = $this->get(route('books.index', ['sort' => 'invalid']));

        $response->assertSessionHasErrors('sort');
    }

    #[TestDox('検索条件はページネーションのリンクに引き継がれる')]
    public function test_index_preserves_query_string_in_pagination_links(): void
    {
        Book::factory()->count(15)->create(['title' => 'キーワード対象の本']);

        $response = $this->get(route('books.index', ['keyword' => 'キーワード']));

        $response->assertOk();
        $response->assertSee('keyword='.urlencode('キーワード'), false);
    }

    // ---------------------------------------------------------------
    // ★ISBN検索（GET /books/isbn/{isbn}）
    // ---------------------------------------------------------------

    #[TestDox('ISBN検索は見つかった書籍情報をJSONで返す')]
    public function test_isbn_search_returns_book_data_when_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    ['volumeInfo' => ['title' => '吾輩は猫である', 'authors' => ['夏目漱石']]],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/books/isbn/9784101010014');

        $response->assertOk();
        $response->assertJsonPath('title', '吾輩は猫である');
        $response->assertJsonPath('author', '夏目漱石');
    }

    #[TestDox('ISBN検索で見つからない場合はerrorキー付きで404を返す')]
    public function test_isbn_search_returns_error_when_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Http::fake([
            'www.googleapis.com/*' => Http::response(['totalItems' => 0], 200),
        ]);

        $response = $this->getJson('/books/isbn/9999999999999');

        $response->assertStatus(404);
        $response->assertJsonStructure(['error']);
    }

    #[TestDox('13桁でないISBNを指定するとerrorキー付きで422を返す')]
    public function test_isbn_search_returns_error_for_invalid_format(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->getJson('/books/isbn/123');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    #[TestDox('著者名が未入力でも書籍を登録できる（★応用：nullable化）')]
    public function test_authenticated_user_can_store_book_without_author(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '著者不明の本',
            'author' => '',
            'isbn' => '1234567890124',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('books', [
            'title' => '著者不明の本',
            'author_name' => null,
        ]);
    }

    #[TestDox('出版日が未入力でも書籍を登録できる（★応用：nullable化）')]
    public function test_authenticated_user_can_store_book_without_published_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => '出版日不明の本',
            'author' => 'テスト太郎',
            'isbn' => '1234567890125',
            'published_date' => '',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('books', [
            'title' => '出版日不明の本',
            'published_date' => null,
        ]);
    }

    #[TestDox('著者名・出版日ともに未入力でも書籍を登録できる（★応用：nullable化）')]
    public function test_authenticated_user_can_store_book_without_author_and_published_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $payload = [
            'title' => 'タイトルのみの本',
            'isbn' => '1234567890126',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('books', [
            'title' => 'タイトルのみの本',
            'author_name' => null,
            'published_date' => null,
        ]);
    }
}
