<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * books テーブルに書籍データを11件投入する。
     * ★応用：登録者は User::first() 固定ではなく、全ユーザーからランダムに割り当てる
     * （マイ読書レポートで複数ユーザーの所有書籍を表示するため）。
     * firstOrCreate（ISBN重複防止）と genres()->sync() を使用。
     */
    public function run(): void
    {
        $users = User::all();

        $books = [
            [
                'no' => 1,
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_at' => '1905-01-01',
                'description' => '飼い猫の視点から人間社会を風刺的に描いた、夏目漱石の代表的な長編小説。',
                'genres' => ['小説'],
            ],
            [
                'no' => 2,
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_at' => '1936-10-01',
                'description' => '人間関係の築き方について具体的な事例をもとに解説した自己啓発書の古典。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'no' => 3,
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_at' => '2012-06-23',
                'description' => '読みやすく保守しやすいコードを書くための実践的なテクニックを紹介する技術書。',
                'genres' => ['技術書'],
            ],
            [
                'no' => 4,
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_at' => '2013-08-30',
                'description' => '成功者に共通する7つの習慣を体系的にまとめた自己啓発の名著。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'no' => 5,
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_at' => '1906-04-01',
                'description' => '江戸っ子気質の青年教師が地方中学校で巻き起こす痛快な物語。',
                'genres' => ['小説'],
            ],
            [
                'no' => 6,
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_at' => '2016-09-08',
                'description' => '認知革命から現代までの人類の歩みを壮大なスケールで描くベストセラー。',
                'genres' => ['歴史', '科学'],
            ],
            [
                'no' => 7,
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_at' => '2017-12-18',
                'description' => '品質の高いコードを書くための原則とプラクティスをまとめたソフトウェア開発者必読書。',
                'genres' => ['技術書'],
            ],
            [
                'no' => 8,
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_at' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式でわかりやすく解説したベストセラー自己啓発書。',
                'genres' => ['自己啓発'],
            ],
            [
                'no' => 9,
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_at' => '2015-03-11',
                'description' => 'お笑い芸人の視点から師弟関係と人生の機微を描いた芥川賞受賞作。',
                'genres' => ['小説'],
            ],
            [
                'no' => 10,
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_at' => '2019-01-11',
                'description' => 'データに基づいて世界を正しく見るための思考法を解説するベストセラー。',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'no' => 11,
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_at' => '2007-01-18',
                'description' => 'コンテナ輸送の発明が世界経済に与えた影響を描くノンフィクション。',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $data) {
            $book = Book::firstOrCreate(
                ['isbn' => $data['isbn']],
                [
                    'user_id' => $users->random()->id, // ★応用：ランダムユーザー割当
                    'title' => $data['title'],
                    'author_name' => $data['author'],
                    'published_date' => $data['published_at'],
                    'description' => $data['description'],
                    'image_url' => "https://placehold.co/200x300/e2e8f0/475569?text={$data['no']}",
                ]
            );

            $genreIds = Genre::whereIn('name', $data['genres'])->pluck('id');
            $book->genres()->sync($genreIds);
        }
    }
}
