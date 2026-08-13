<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('ログイン済みユーザーはレビューにいいねできる')]
    public function test_authenticated_user_can_like_a_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $response = $this->actingAs($user)->post(route('reviews.like', $review));

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
        $response->assertRedirect();
    }

    #[TestDox('同じレビューに二重でいいねしても重複登録されない（トグルで解除される）')]
    public function test_liking_an_already_liked_review_does_not_duplicate(): void
    {
        // likesテーブルは (user_id, review_id) の複合主キーのため、
        // 二重登録はDBレベルでも一意制約により防がれる。
        // トグル方式なので、2回目のリクエストで解除される。
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review);

        $this->actingAs($user)->post(route('reviews.like', $review));

        $this->assertDatabaseCount('likes', 0);
    }

    #[TestDox('いいね済みのレビューはいいねを取り消せる')]
    public function test_authenticated_user_can_unlike_a_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review);

        $response = $this->actingAs($user)->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
        $response->assertRedirect();
    }

    #[TestDox('未ログイン状態ではいいねできず、ログイン画面にリダイレクトされる')]
    public function test_guest_cannot_like_a_review(): void
    {
        $review = Review::factory()->create();

        $response = $this->post(route('reviews.like', $review));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('likes', 0);
    }
}
