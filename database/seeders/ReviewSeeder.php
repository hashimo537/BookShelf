<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * ★応用：評価ごとの汎用日本語コメントテンプレート（5段階）。
     * マイ読書レポートの評価分布グラフが意味のある分布になるよう、
     * 個別の書籍名に依存しない簡潔な文言に統一する。
     */
    private const COMMENT_TEMPLATES = [
        1 => 'あまり期待通りではありませんでした。',
        2 => '正直なところ、あまり面白くありませんでした。',
        3 => '普通でした。可もなく不可もなくという印象です。',
        4 => 'なかなか良かったです。人におすすめできる内容でした。',
        5 => '非常に素晴らしい内容でした。強くおすすめします。',
    ];

    /**
     * reviews テーブルにレビューデータを投入する。
     * ★応用：
     * - 評価は1〜5の全範囲（基本機能版の3〜5から拡大）
     * - コメントは評価別の日本語テンプレートに統一
     * - 各書籍のレビュー件数は2〜4件でランダム
     * - 投稿者もランダム（同一書籍内で重複しないよう選出）
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        foreach ($books as $book) {
            $reviewCount = random_int(2, 4);
            $reviewers = $users->random(min($reviewCount, $users->count()));

            foreach ($reviewers as $reviewer) {
                $rating = random_int(1, 5);

                Review::create([
                    'user_id' => $reviewer->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => self::COMMENT_TEMPLATES[$rating],
                ]);
            }
        }
    }
}
