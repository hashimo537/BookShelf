<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('未ログイン状態ではマイ読書レポートにアクセスできない')]
    public function test_guest_cannot_view_report(): void
    {
        $response = $this->get(route('reports.index'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーはマイ読書レポートを表示できる')]
    public function test_authenticated_user_can_view_report(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertOk();
        $response->assertViewIs('reports.index');
    }

    #[TestDox('基本統計（総レビュー数・読了冊数・平均評価）が正しく集計される')]
    public function test_summary_stats_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        Review::factory()->create(['user_id' => $user->id, 'book_id' => $bookA->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $bookB->id, 'rating' => 3]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['summary']['total_reviews'] === 2
                && $stats['summary']['books_read'] === 2
                && $stats['summary']['average_rating'] === 4.0; // (5+3)/2
        });
    }

    #[TestDox('レビューが1件も無い場合、平均評価は0として返る（Blade側で「-」表示される）')]
    public function test_average_rating_is_zero_when_no_reviews(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', fn($stats) => $stats['summary']['average_rating'] === 0.0);
    }

    #[TestDox('同じ書籍に複数回レビューしても、読了冊数は重複せず1冊として数えられる')]
    public function test_books_read_counts_distinct_books_only(): void
    {
        // 同一書籍に対する複数レビューは想定していないが、念のためbook_idの重複排除を確認する
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', fn($stats) => $stats['summary']['books_read'] === 1);
    }

    #[TestDox('評価分布は1〜5の各評価件数を正しく集計する')]
    public function test_rating_distribution_counts_each_rating(): void
    {
        $user = User::factory()->create();
        Review::factory()->create(['user_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'rating' => 3]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            $distribution = $stats['rating_distribution'];

            // インデックス0=評価1, ..., インデックス4=評価5
            return $distribution[4] === 2 && $distribution[2] === 1 && $distribution[0] === 0;
        });
    }

    #[TestDox('高評価書籍TOP5には評価4以上のレビューのみ含まれる')]
    public function test_top_rated_books_only_includes_four_and_five_star_reviews(): void
    {
        $user = User::factory()->create();
        $lowRatedBook = Book::factory()->create(['title' => '低評価の本']);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $lowRatedBook->id, 'rating' => 3]);

        $highRatedBook = Book::factory()->create(['title' => '高評価の本']);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $highRatedBook->id, 'rating' => 5]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) use ($highRatedBook, $lowRatedBook) {
            $titles = collect($stats['top_rated_books'])->pluck('title');

            return $titles->contains('高評価の本') && !$titles->contains('低評価の本');
        });
    }

    #[TestDox('高評価書籍TOP5は最大5件に制限される')]
    public function test_top_rated_books_limited_to_five(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 7) as $i) {
            $book = Book::factory()->create();
            Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'rating' => 5]);
        }

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', fn($stats) => count($stats['top_rated_books']) === 5);
    }

    #[TestDox('4以上の評価が1件も無い場合、高評価書籍TOP5は空になる')]
    public function test_top_rated_books_is_empty_when_no_high_ratings(): void
    {
        $user = User::factory()->create();
        Review::factory()->create(['user_id' => $user->id, 'rating' => 2]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', fn($stats) => count($stats['top_rated_books']) === 0);
        $response->assertSee('4星以上の書籍がありません');
    }

    #[TestDox('ジャンル別評価傾向は正しい平均評価・件数で集計される')]
    public function test_genre_ratings_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '技術書']);

        $bookA = Book::factory()->create();
        $bookA->genres()->attach($genre);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $bookA->id, 'rating' => 5]);

        $bookB = Book::factory()->create();
        $bookB->genres()->attach($genre);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $bookB->id, 'rating' => 3]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            $genreStat = collect($stats['genre_ratings'])->firstWhere('name', '技術書');

            return $genreStat !== null
                && $genreStat['count'] === 2
                && $genreStat['average_rating'] === 4.0; // (5+3)/2
        });
    }

    #[TestDox('ジャンルが設定された書籍のレビューが無い場合、案内メッセージが表示される')]
    public function test_genre_ratings_shows_empty_message_when_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', fn($stats) => count($stats['genre_ratings']) === 0);
        $response->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    #[TestDox('ジャンル別評価傾向は最大5件に制限される')]
    public function test_genre_ratings_limited_to_five(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 7) as $i) {
            $genre = Genre::factory()->create();
            $book = Book::factory()->create();
            $book->genres()->attach($genre);
            Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'rating' => 4]);
        }

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', fn($stats) => count($stats['genre_ratings']) === 5);
    }

    #[TestDox('レポートには自分のレビューのみが反映され、他人のレビューは含まれない')]
    public function test_report_only_reflects_the_authenticated_users_own_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Review::factory()->create(['user_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $otherUser->id, 'rating' => 1]);
        Review::factory()->create(['user_id' => $otherUser->id, 'rating' => 1]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['summary']['total_reviews'] === 1
                && $stats['summary']['average_rating'] === 5.0;
        });
    }
}
