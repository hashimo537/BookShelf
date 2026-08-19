<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート（GET /reports）
     * 認証必須。ログインユーザー自身のレビューを基に4種類の集計を表示する。
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
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function buildSummary($reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => $reviews->isEmpty() ? 0.0 : round($reviews->avg('rating'), 1),
        ];
    }

    /**
     * 評価1〜5ごとのレビュー件数を集計する。
     * インデックス0〜4がそれぞれ評価1〜5に対応する（Blade側で $index + 1 として使用）。
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function buildRatingDistribution($reviews)
    {
        return collect(range(1, 5))
            ->map(fn(int $rating) => $reviews->where('rating', $rating)->count());
    }

    /**
     * 4以上の評価を付けた書籍を上位5件抽出する。
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return array<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function buildTopRatedBooks($reviews): array
    {
        return $reviews
            ->where('rating', '>=', 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn($review) => [
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
     * 1件のレビューは、紐づく全ジャンルの集計対象に含まれる（多対多のため）。
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return array<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function buildGenreRatings($reviews): array
    {
        return $reviews
            ->flatMap(fn($review) => $review->book->genres->map(fn($genre) => [
                'genre' => $genre,
                'rating' => $review->rating,
            ]))
            ->groupBy(fn($item) => $item['genre']->id)
            ->map(function ($items) {
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
