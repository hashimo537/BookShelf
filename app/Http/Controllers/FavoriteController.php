<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧（GET /favorites）
     * 認証必須。ログインユーザーがお気に入り登録した書籍を
     * 10件ずつページネーションして表示する。
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('books.created_at')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入り登録・解除のトグル（POST /books/{book}/favorites）
     * 認証必須。
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $result = $request->user()->favoriteBooks()->toggle($book->id);

        $message = !empty($result['attached'])
            ? 'お気に入りに追加しました。'
            : 'お気に入りから削除しました。';

        return back()->with('success', $message);
    }
}
