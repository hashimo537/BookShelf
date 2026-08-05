<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * favorites テーブルにお気に入りデータを投入する。
     * 各ユーザーに3〜5冊のお気に入りを設定。
     * syncWithoutDetaching を使用。
     */
    public function run(): void
    {
        // email => [ISBNのリスト]（3〜5冊）
        $favorites = [
            'yamada@example.com' => [
                '9784101010014', // 吾輩は猫である
                '9784873115658', // リーダブルコード
                '9784309226712', // サピエンス全史
                '9784048930598', // Clean Code
                '9784822289607', // FACTFULNESS
            ],
            'suzuki@example.com' => [
                '9784422100524', // 人を動かす
                '9784863940246', // 7つの習慣
                '9784478025819', // 嫌われる勇気
                '9784163902302', // 火花
            ],
            'tanaka@example.com' => [
                '9784873115658', // リーダブルコード
                '9784309226712', // サピエンス全史
                '9784048930598', // Clean Code
            ],
            'sato@example.com' => [
                '9784422100524', // 人を動かす
                '9784101010021', // 坊っちゃん
                '9784822289607', // FACTFULNESS
                '9784822251468', // コンテナ物語
            ],
            'takahashi@example.com' => [
                '9784101010014', // 吾輩は猫である
                '9784863940246', // 7つの習慣
                '9784048930598', // Clean Code
                '9784478025819', // 嫌われる勇気
                '9784163902302', // 火花
            ],
        ];

        foreach ($favorites as $email => $isbns) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                continue;
            }

            $bookIds = Book::whereIn('isbn', $isbns)->pluck('id');

            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
