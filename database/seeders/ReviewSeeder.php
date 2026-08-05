<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * reviews テーブルにレビューデータを32件投入する。
     * 5人のユーザーが11冊の書籍に対してレビューを投稿。
     * 各書籍に2〜4件のレビューを配分（合計32件）。
     * create を使用。
     */
    public function run(): void
    {
        // ISBN => [ [email, rating, comment], ... ]
        $reviews = [
            '9784101010014' => [ // 吾輩は猫である（3件）
                ['yamada@example.com', 5, '猫の視点から人間社会を風刺していて非常に面白かったです。'],
                ['suzuki@example.com', 4, '文体が独特で読みやすいとは言えませんが、名作だと思います。'],
                ['tanaka@example.com', 4, '皮肉のきいたユーモアが随所に光る作品でした。'],
            ],
            '9784422100524' => [ // 人を動かす（3件）
                ['suzuki@example.com', 5, '人間関係の本質を突いた内容で、仕事にもすぐ活かせました。'],
                ['sato@example.com', 4, '具体例が豊富でわかりやすかったです。'],
                ['takahashi@example.com', 5, '何度読み返しても新しい発見がある名著です。'],
            ],
            '9784873115658' => [ // リーダブルコード（4件）
                ['tanaka@example.com', 5, 'エンジニア必読の一冊。命名規則の重要性を再認識しました。'],
                ['yamada@example.com', 5, '実務で即使えるテクニックが満載でした。'],
                ['sato@example.com', 4, '図解が多く初心者にもわかりやすい内容です。'],
                ['takahashi@example.com', 4, 'コードレビューの基準として社内で共有しました。'],
            ],
            '9784863940246' => [ // 7つの習慣（3件）
                ['yamada@example.com', 5, '自己啓発書全体の原点とも言える一冊だと感じました。'],
                ['suzuki@example.com', 3, '内容は良いですが少し冗長に感じました。'],
                ['tanaka@example.com', 4, '習慣化の考え方が仕事にもプライベートにも役立ちました。'],
            ],
            '9784101010021' => [ // 坊っちゃん（2件）
                ['sato@example.com', 4, '痛快な展開で一気に読み終えました。'],
                ['takahashi@example.com', 3, 'テンポは良いですが少し古臭さも感じました。'],
            ],
            '9784309226712' => [ // サピエンス全史（3件）
                ['yamada@example.com', 5, '人類史をこれほど壮大に描いた本は初めてです。'],
                ['tanaka@example.com', 5, '歴史と科学の両面から人類を捉える視点が新鮮でした。'],
                ['suzuki@example.com', 4, 'ボリュームがありますが読み応え十分でした。'],
            ],
            '9784048930598' => [ // Clean Code（4件）
                ['takahashi@example.com', 5, 'コーディング規約を見直すきっかけになりました。'],
                ['yamada@example.com', 4, '内容は少し古い部分もありますが基本は今も通用します。'],
                ['sato@example.com', 5, 'チーム全体で読むべき一冊だと思います。'],
                ['suzuki@example.com', 3, 'サンプルコードがJavaなので他言語だとやや読みにくいです。'],
            ],
            '9784478025819' => [ // 嫌われる勇気（3件）
                ['tanaka@example.com', 5, '対話形式で読みやすく、アドラー心理学の入門に最適です。'],
                ['sato@example.com', 4, '考え方が大きく変わるきっかけになりました。'],
                ['takahashi@example.com', 4, '自己受容について深く考えさせられました。'],
            ],
            '9784163902302' => [ // 火花（2件）
                ['yamada@example.com', 4, '芥川賞受賞作らしい繊細な描写が印象的でした。'],
                ['suzuki@example.com', 4, 'お笑いの世界のリアルさが伝わってきました。'],
            ],
            '9784822289607' => [ // FACTFULNESS（3件）
                ['sato@example.com', 5, '思い込みを覆されるデータが多く勉強になりました。'],
                ['takahashi@example.com', 5, '世界の見方が変わる一冊でした。'],
                ['tanaka@example.com', 4, '統計の読み方について学びが多かったです。'],
            ],
            '9784822251468' => [ // コンテナ物語（2件）
                ['yamada@example.com', 4, '物流の裏側を知ることができる興味深い内容でした。'],
                ['sato@example.com', 3, '専門的な部分もありますが全体として読みやすかったです。'],
            ],
        ];

        foreach ($reviews as $isbn => $bookReviews) {
            $book = Book::where('isbn', $isbn)->first();

            if (!$book) {
                continue;
            }

            foreach ($bookReviews as [$email, $rating, $comment]) {
                $user = User::where('email', $email)->first();

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => $comment,
                ]);
            }
        }
    }
}
