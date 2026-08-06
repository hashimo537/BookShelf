<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 投稿（store）
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_store_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $payload = [
            'rating' => 5,
            'comment' => 'とても面白かったです。',
        ];

        $response = $this->actingAs($user)->post(route('reviews.store', $book), $payload);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'とても面白かったです。',
        ]);

        $response->assertRedirect(route('books.show', $book));
    }

    public function test_guest_cannot_store_review(): void
    {
        $book = Book::factory()->create();

        $payload = [
            'rating' => 5,
            'comment' => 'ゲストが投稿しようとした',
        ];

        $response = $this->post(route('reviews.store', $book), $payload);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_can_review_their_own_book(): void
    {
        // PM確認済み：自分が登録した書籍にも自分でレビューできる
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $payload = [
            'rating' => 4,
            'comment' => '自分の本ですが感想を書きます。',
        ];

        $response = $this->actingAs($user)->post(route('reviews.store', $book), $payload);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
        $response->assertRedirect(route('books.show', $book));
    }

    // ---------------------------------------------------------------
    // バリデーション（StoreReviewRequest）
    // ---------------------------------------------------------------

    public function test_store_fails_when_rating_is_missing(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $payload = [
            'rating' => '',
            'comment' => 'コメントのみ',
        ];

        $response = $this->actingAs($user)->post(route('reviews.store', $book), $payload);

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_store_fails_when_comment_is_missing(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $payload = [
            'rating' => 3,
            'comment' => '',
        ];

        $response = $this->actingAs($user)->post(route('reviews.store', $book), $payload);

        $response->assertSessionHasErrors('comment');
    }

    public function test_store_fails_when_rating_is_out_of_range(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $payload = [
            'rating' => 6, // 1〜5の範囲外
            'comment' => '評価が範囲外です。',
        ];

        $response = $this->actingAs($user)->post(route('reviews.store', $book), $payload);

        $response->assertSessionHasErrors('rating');
    }

    public function test_store_fails_when_comment_exceeds_max_length(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $payload = [
            'rating' => 3,
            'comment' => str_repeat('あ', 1001), // 1000文字超
        ];

        $response = $this->actingAs($user)->post(route('reviews.store', $book), $payload);

        $response->assertSessionHasErrors('comment');
    }

    // ---------------------------------------------------------------
    // 編集画面（認証＋認可）
    // ---------------------------------------------------------------

    public function test_author_can_view_edit_page(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reviews.edit', $review));

        $response->assertOk();
        $response->assertViewIs('reviews.edit');
    }

    public function test_non_author_cannot_view_edit_page(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $author->id]);

        $response = $this->actingAs($otherUser)->get(route('reviews.edit', $review));

        $response->assertForbidden();
    }

    // ---------------------------------------------------------------
    // 更新（update）
    // ---------------------------------------------------------------

    public function test_author_can_update_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);

        $payload = [
            'rating' => 2,
            'comment' => '更新後のコメントです。',
        ];

        $response = $this->actingAs($user)->put(route('reviews.update', $review), $payload);

        $response->assertRedirect(route('books.show', $review->book));
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 2,
            'comment' => '更新後のコメントです。',
        ]);
    }

    public function test_non_author_cannot_update_review(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $author->id]);

        $payload = [
            'rating' => 1,
            'comment' => '不正な更新',
        ];

        $response = $this->actingAs($otherUser)->put(route('reviews.update', $review), $payload);

        $response->assertForbidden();
        $this->assertDatabaseMissing('reviews', ['comment' => '不正な更新']);
    }

    // ---------------------------------------------------------------
    // 削除（destroy）
    // ---------------------------------------------------------------

    public function test_author_can_delete_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);
        $book = $review->book;

        $response = $this->actingAs($user)->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_non_author_cannot_delete_review(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $author->id]);

        $response = $this->actingAs($otherUser)->delete(route('reviews.destroy', $review));

        $response->assertForbidden();
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_guest_cannot_delete_review(): void
    {
        $review = Review::factory()->create();

        $response = $this->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    // ---------------------------------------------------------------
    // 平均点
    // ---------------------------------------------------------------

    public function test_book_average_rating_is_updated_after_review_posted(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $book = Book::factory()->create();

        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $user1->id,
            'rating' => 4,
        ]);

        $this->actingAs($user2)->post(route('reviews.store', $book), [
            'rating' => 2,
            'comment' => '普通でした',
        ]);

        $book->refresh();

        $average = $book->reviews()->avg('rating');

        $response = $this->get(route('books.show', $book));

        $response->assertSee('3.0');

        $this->assertEquals(3.0, (float) $average);

    }


    // ---------------------------------------------------------------
    // ゲストは編集画面に入れない
    // ---------------------------------------------------------------

    public function test_guest_cannot_view_edit_page(): void
    {
        $review = Review::factory()->create();

        $response = $this->get(route('reviews.edit', $review));

        $response->assertRedirect(route('login'));
    }
}
