<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('ログイン済みユーザーは書籍をお気に入り登録できる')]
    public function test_authenticated_user_can_favorite_a_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
        $response->assertRedirect();
    }

    #[TestDox('お気に入り済み書籍を再登録しても重複しない（トグルで解除される）')]
    public function test_favoriting_an_already_favorited_book_does_not_duplicate(): void
    {
        // トグル方式のため、既にお気に入り済みの書籍にもう一度リクエストすると解除される
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book);

        $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    #[TestDox('お気に入りを解除できる')]
    public function test_authenticated_user_can_unfavorite_a_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book);

        $response = $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
        $response->assertRedirect();
    }

    #[TestDox('未ログイン状態ではお気に入り登録できず、ログイン画面にリダイレクトされる')]
    public function test_guest_cannot_favorite_a_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('favorites', 0);
    }

    #[TestDox('未ログイン状態ではお気に入り一覧画面にアクセスできない')]
    public function test_guest_cannot_view_favorites_index(): void
    {
        $response = $this->get(route('favorites.index'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('お気に入り一覧には自分が登録した書籍だけが表示される')]
    public function test_favorites_index_shows_only_the_users_favorited_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $favoritedBook = Book::factory()->create(['title' => 'お気に入りの本']);
        $user->favoriteBooks()->attach($favoritedBook);

        $notFavoritedBook = Book::factory()->create(['title' => '登録していない本']);

        $othersFavoritedBook = Book::factory()->create(['title' => '他人がお気に入りの本']);
        $otherUser->favoriteBooks()->attach($othersFavoritedBook);

        $response = $this->actingAs($user)->get(route('favorites.index'));

        $response->assertOk();
        $response->assertSee('お気に入りの本');
        $response->assertDontSee('登録していない本');
        $response->assertDontSee('他人がお気に入りの本');
    }

    #[TestDox('お気に入り一覧は書籍一覧と同じ新しい順（id降順で確定）で表示される')]
    public function test_favorites_index_orders_books_consistently(): void
    {
        $user = User::factory()->create();

        $olderBook = Book::factory()->create(['title' => '先に登録した本']);
        $newerBook = Book::factory()->create(['title' => '後に登録した本']);

        $user->favoriteBooks()->attach([$olderBook->id, $newerBook->id]);

        $response = $this->actingAs($user)->get(route('favorites.index'));

        $response->assertViewHas('books', function ($books) use ($newerBook, $olderBook) {
            $ids = collect($books->items())->pluck('id')->toArray();

            return array_search($newerBook->id, $ids) < array_search($olderBook->id, $ids);
        });
    }
}
