<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * ジャンル一覧（GET /genres）
     * 認証必須。各ジャンルの書籍数を表示する。
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル登録画面（GET /genres/create）
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンル登録処理（POST /genres）
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Genre::create([
            'user_id' => $request->user()->id, // 登録者の記録用（編集・削除の権限には使用しない）
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    /**
     * ジャンル詳細画面（GET /genres/{genre}）
     * 認証必須。ジャンルに紐づく書籍を10件ずつページネーションして表示する。
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->orderByDesc('books.created_at')
            ->orderByDesc('books.id') // 同一秒作成でも並び順を確定させる
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンル編集画面（GET /genres/{genre}/edit）
     * 認証必須。所有者制限なし（PM確認済み）。
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンル更新処理（PUT /genres/{genre}）
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $validated = $request->validated();

        $genre->update([
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンル削除処理（DELETE /genres/{genre}）
     * book_genre.genre_id は restrictOnDelete() のため、
     * 使用中（紐づく書籍がある）ジャンルを削除しようとすると QueryException が発生する。
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        try {
            $genre->delete();
        } catch (QueryException) {
            return redirect()
                ->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐づいている為削除できません。');
        }

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
