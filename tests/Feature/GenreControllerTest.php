<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 一覧・詳細（認証必須・所有者制限なし）
    // ---------------------------------------------------------------

    #[TestDox('未ログイン状態ではジャンル一覧にアクセスできない')]
    public function test_guest_cannot_view_genre_index(): void
    {
        $response = $this->get(route('genres.index'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーはジャンル一覧を表示できる')]
    public function test_authenticated_user_can_view_genre_index(): void
    {
        $user = User::factory()->create();
        Genre::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('genres.index'));

        $response->assertOk();
        $response->assertViewIs('genres.index');
    }

    #[TestDox('未ログイン状態ではジャンル詳細にアクセスできない')]
    public function test_guest_cannot_view_genre_show(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->get(route('genres.show', $genre));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーはジャンル詳細を表示できる')]
    public function test_authenticated_user_can_view_genre_show(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->get(route('genres.show', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.show');
        $response->assertSee($genre->name);
    }

    // ---------------------------------------------------------------
    // ジャンル絞り込み（genres.show が該当ジャンルの書籍だけを表示するか）
    // ---------------------------------------------------------------

    #[TestDox('ジャンル詳細画面は、そのジャンルに紐づく書籍だけを表示する')]
    public function test_genre_show_only_lists_books_belonging_to_that_genre(): void
    {
        $user = User::factory()->create();

        $targetGenre = Genre::factory()->create(['name' => '技術書']);
        $otherGenre = Genre::factory()->create(['name' => '小説']);

        $matchingBook = Book::factory()->create(['title' => '対象ジャンルの本']);
        $matchingBook->genres()->attach($targetGenre);

        $unrelatedBook = Book::factory()->create(['title' => '無関係なジャンルの本']);
        $unrelatedBook->genres()->attach($otherGenre);

        $response = $this->actingAs($user)->get(route('genres.show', $targetGenre));

        $response->assertOk();
        $response->assertSee('対象ジャンルの本');
        $response->assertDontSee('無関係なジャンルの本');
    }

    #[TestDox('ジャンル詳細画面の書籍一覧は10件ずつページネーションされる')]
    public function test_genre_show_paginates_books_by_ten(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 11冊紐付けて、1ページ目には10冊だけ表示されることを確認する
        $books = Book::factory()->count(11)->create();
        foreach ($books as $book) {
            $book->genres()->attach($genre);
        }

        $response = $this->actingAs($user)->get(route('genres.show', $genre));

        $response->assertOk();
        $response->assertViewHas('books', function ($books) {
            return $books->count() === 10 && $books->total() === 11;
        });
    }

    // ---------------------------------------------------------------
    // 登録（store）
    // ---------------------------------------------------------------

    #[TestDox('未ログイン状態ではジャンル登録画面にアクセスできない')]
    public function test_guest_cannot_view_create_page(): void
    {
        $response = $this->get(route('genres.create'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーはジャンルを登録できる')]
    public function test_authenticated_user_can_store_genre(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => 'ミステリー',
        ]);

        $this->assertDatabaseHas('genres', [
            'name' => 'ミステリー',
            'user_id' => $user->id,
        ]);
        $response->assertRedirect(route('genres.index'));
    }

    #[TestDox('未ログイン状態ではジャンルを登録できない')]
    public function test_guest_cannot_store_genre(): void
    {
        $response = $this->post(route('genres.store'), [
            'name' => 'ゲストが登録しようとしたジャンル',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('genres', 0);
    }

    #[TestDox('ジャンル名が未入力の場合は登録に失敗する')]
    public function test_store_fails_when_name_is_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);
        $this->assertDatabaseCount('genres', 0);
    }

    #[TestDox('ジャンル名が255文字を超える場合は登録に失敗する')]
    public function test_store_fails_when_name_exceeds_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => str_repeat('あ', 256),
        ]);

        $response->assertSessionHasErrors(['name' => '255文字以内で入力してください。']);
    }

    #[TestDox('既に存在するジャンル名で登録しようとすると、重複エラーの文言付きで失敗する')]
    public function test_store_fails_when_name_already_exists(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '小説']);

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => '小説',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'このジャンル名は既に使用されています。',
        ]);
        $this->assertDatabaseCount('genres', 1); // 重複分は登録されていない
    }

    // ---------------------------------------------------------------
    // 編集・更新（所有者制限なし：PM確認済み）
    // ---------------------------------------------------------------

    #[TestDox('未ログイン状態ではジャンル編集画面にアクセスできない')]
    public function test_guest_cannot_view_edit_page(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->get(route('genres.edit', $genre));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('作成者本人でなくても、ログイン済みなら誰でもジャンル編集画面を開ける')]
    public function test_any_authenticated_user_can_view_edit_page(): void
    {
        // 作成者ではない別のユーザーでも編集画面を開けることを確認
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($otherUser)->get(route('genres.edit', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.edit');
    }

    #[TestDox('作成者本人でなくても、ログイン済みなら誰でもジャンルを更新できる')]
    public function test_any_authenticated_user_can_update_genre(): void
    {
        // 作成者ではない別のユーザーでも更新できることを確認（PM確認済み仕様）
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create(['user_id' => $creator->id, 'name' => '元の名前']);

        $response = $this->actingAs($otherUser)->put(route('genres.update', $genre), [
            'name' => '更新後の名前',
        ]);

        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '更新後の名前',
        ]);
    }

    #[TestDox('未ログイン状態ではジャンルを更新できない')]
    public function test_guest_cannot_update_genre(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->put(route('genres.update', $genre), [
            'name' => 'ゲストによる更新',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('genres', ['name' => 'ゲストによる更新']);
    }

    #[TestDox('他のジャンルと同じ名前に更新しようとすると、重複エラーの文言付きで失敗する')]
    public function test_update_fails_when_name_duplicates_another_genre(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '技術書']);
        $genre = Genre::factory()->create(['name' => '自己啓発']);

        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '技術書', // 他のジャンルと重複
        ]);

        $response->assertSessionHasErrors([
            'name' => 'このジャンル名は既に使用されています。',
        ]);
    }

    #[TestDox('自分自身の現在の名前と同じ値で更新しても一意性エラーにならない')]
    public function test_update_succeeds_when_name_is_unchanged(): void
    {
        // 自分自身の現在の名前と同じ値で更新しても一意性エラーにならないことを確認
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'エッセイ']);

        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => 'エッセイ',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('genres.index'));
    }

    // ---------------------------------------------------------------
    // 削除（destroy）
    // ---------------------------------------------------------------

    #[TestDox('作成者本人でなくても、ログイン済みなら未使用のジャンルを削除できる')]
    public function test_any_authenticated_user_can_delete_unused_genre(): void
    {
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($otherUser)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    #[TestDox('書籍に紐づいているジャンルを削除しようとすると、例外にはならずエラーメッセージ付きで失敗する')]
    public function test_deleting_genre_still_used_by_a_book_fails_gracefully(): void
    {
        // book_genre.genre_id は restrictOnDelete() のため、
        // 書籍に紐づくジャンルを削除しようとすると失敗し、
        // 例外にはならずエラーメッセージ付きでリダイレクトされることを確認する。
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre);

        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('genres', ['id' => $genre->id]); // 削除されていない
    }

    #[TestDox('未ログイン状態ではジャンルを削除できない')]
    public function test_guest_cannot_delete_genre(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
