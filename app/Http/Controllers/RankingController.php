<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキング TOP10（GET /ranking）
     * 公開ページ（ゲスト可）。
     * レビュー平均評価が高い順に10冊表示する。
     * レビューが1件も無い書籍はランキングから除外する。
     */
    public function index(): View
    {
        $rankedBooks = Book::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->having('reviews_count', '>', 0) // レビュー0件の書籍を除外
            ->orderByDesc('reviews_avg_rating')
            ->take(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
