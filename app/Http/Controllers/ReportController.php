<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート（GET /reports）
     * 認証必須。ログインユーザー自身のレビューを基に4種類の集計を表示する。
     *
     * @param  Request  $request  現在のHTTPリクエスト（認証済みユーザーの取得に使用）
     * @return View 基本統計・評価分布・高評価書籍TOP5・ジャンル別評価TOP5を渡すビュー
     */
    public function index(Request $request): View
    {
        $reviews = $request->user()
            ->reviews()
            ->with('book.genres')
            ->get();

        $stats = [
            'summary' => $this->buildSummary($reviews),
            'rating_distribution' => $this->buildRatingDistribution($reviews),
            'top_rated_books' => $this->buildTopRatedBooks($reviews),
            'genre_ratings' => $this->buildGenreRatings($reviews),
        ];

        return view('reports.index', compact('stats'));
    }

    /**
     * 基本サマリー（総レビュー数・読了冊数・平均評価）を集計する。
     * 読了冊数は「レビューした書籍のユニーク数」を代理指標として使用する。
     * 平均評価はレビューが1件も無い場合は0.0を返す（Blade側で「-」表示に変換される）。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧（book・genresをEagerLoad済み）
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function buildSummary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => $reviews->isEmpty() ? 0.0 : round($reviews->avg('rating'), 1),
        ];
    }

    /**
     * 評価1〜5ごとのレビュー件数を集計する。
     * 返り値のインデックス0〜4がそれぞれ評価1〜5に対応する（Blade側で $index + 1 として使用）。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧
     * @return Collection<int, int> インデックス0〜4に評価1〜5それぞれの件数を格納したコレクション
     */
    private function buildRatingDistribution(Collection $reviews): Collection
    {
        return collect(range(1, 5))
            ->map(fn (int $rating): int => $reviews->where('rating', $rating)->count());
    }

    /**
     * 4以上の評価を付けた書籍を、評価が高い順に上位5件抽出する。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧（bookをEagerLoad済み）
     * @return array<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function buildTopRatedBooks(Collection $reviews): array
    {
        return $reviews
            ->where('rating', '>=', 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn (Review $review): array => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values()
            ->all();
    }

    /**
     * ジャンルごとの平均評価・レビュー件数を集計し、平均評価が高い順に上位5件を返す。
     * 1件のレビューは、紐づく全ジャンルの集計対象に含まれる（書籍とジャンルは多対多のため）。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧（book.genresをEagerLoad済み）
     * @return array<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function buildGenreRatings(Collection $reviews): array
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(fn ($genre): array => [
                'genre' => $genre,
                'rating' => $review->rating,
            ]))
            ->groupBy(fn (array $item) => $item['genre']->id)
            ->map(function (Collection $items): array {
                $genre = $items->first()['genre'];
                $ratings = $items->pluck('rating');

                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'count' => $ratings->count(),
                    'average_rating' => round($ratings->avg(), 1),
                ];
            })
            ->sortByDesc('average_rating')
            ->take(5)
            ->values()
            ->all();
    }
}
