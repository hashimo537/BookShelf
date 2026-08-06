<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 一覧・詳細（認証必須・所有者制限なし）
    // ---------------------------------------------------------------

    public function test_guest_cannot_view_genre_index(): void
    {
        $response = $this->get(route('genres.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_genre_index(): void
    {
        $user = User::factory()->create();
        Genre::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('genres.index'));

        $response->assertOk();
        $response->assertViewIs('genres.index');
    }

    public function test_guest_cannot_view_genre_show(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->get(route('genres.show', $genre));

        $response->assertRedirect(route('login'));
    }

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
    // ③ ジャンル絞り込み（genres.show が該当ジャンルの書籍だけを表示するか）
    // ---------------------------------------------------------------

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

    public function test_guest_cannot_view_create_page(): void
    {
        $response = $this->get(route('genres.create'));

        $response->assertRedirect(route('login'));
    }

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

    public function test_guest_cannot_store_genre(): void
    {
        $response = $this->post(route('genres.store'), [
            'name' => 'ゲストが登録しようとしたジャンル',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('genres', 0);
    }

    public function test_store_fails_when_name_is_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);
        $this->assertDatabaseCount('genres', 0);
    }

    public function test_store_fails_when_name_exceeds_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => str_repeat('あ', 256),
        ]);

        $response->assertSessionHasErrors(['name' => '255文字以内で入力してください。']);
    }

    // ① 重複登録エラーの文言検証
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

    public function test_guest_cannot_view_edit_page(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->get(route('genres.edit', $genre));

        $response->assertRedirect(route('login'));
    }

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

    public function test_guest_cannot_update_genre(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->put(route('genres.update', $genre), [
            'name' => 'ゲストによる更新',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('genres', ['name' => 'ゲストによる更新']);
    }

    // ① 重複登録エラーの文言検証（更新時）
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

    public function test_any_authenticated_user_can_delete_unused_genre(): void
    {
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($otherUser)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

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

    public function test_guest_cannot_delete_genre(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
