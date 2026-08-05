<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * review_likes テーブルにいいねデータを投入する。
     * 各レビューに0〜3人のユーザーがいいね（自分のレビューを除く）。
     * syncWithoutDetaching を使用。
     */
    public function run(): void
    {
        $allUserIds = User::pluck('id')->toArray();
        $reviews = Review::all();

        foreach ($reviews as $review) {
            // レビュー投稿者本人を除いたユーザー一覧
            $candidateUserIds = array_values(array_diff($allUserIds, [$review->user_id]));

            if (empty($candidateUserIds)) {
                continue;
            }

            // 0〜3人（候補人数を超えないように）ランダムに選出
            $likeCount = random_int(0, min(3, count($candidateUserIds)));

            if ($likeCount === 0) {
                continue;
            }

            shuffle($candidateUserIds);
            $likerIds = array_slice($candidateUserIds, 0, $likeCount);

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        }
    }
}
