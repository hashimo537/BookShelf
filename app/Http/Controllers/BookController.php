<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧（トップページ / GET /books）
     * 公開ページ（ゲスト可）。
     * 最新順・10件ずつページネーションして表示する。
     */
    public function index(): View
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->latest() // created_at の降順（最新登録順）
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 書籍登録画面
     * 全ジャンル一覧をチェックボックスで表示する。
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍登録処理
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'author_name' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細画面
     * 公開ページ（ゲスト可）。
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn($query) => $query->latest(),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集画面
     * 認証＋認可必須（作成者のみ）。
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book); // 本人でなければ403

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍更新処理
     * 認証＋認可必須（作成者のみ）。
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validated();

        $book->update([
            'title' => $validated['title'],
            'author_name' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * 書籍削除処理
     * 認証＋認可必須（作成者のみ）。
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
