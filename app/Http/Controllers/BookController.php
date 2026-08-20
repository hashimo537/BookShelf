<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookIndexRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧（トップページ / GET /books）
     * 公開ページ（ゲスト可）。
     * ★キーワード検索・ジャンル絞り込み・並び替えに対応し、
     * 検索条件はページネーションのリンクにも引き継がれる。
     */
    public function index(BookIndexRequest $request): View
    {
        $validated = $request->validated();

        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author_name', 'like', "%{$keyword}%");
                });
            })
            ->when($validated['genre'] ?? null, function ($query, $genreId) {
                $query->whereHas('genres', fn ($q) => $q->where('genres.id', $genreId));
            })
            ->when(
                ($validated['sort'] ?? 'newest') === 'oldest',
                fn ($query) => $query->orderBy('created_at')->orderBy('id')
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'rating',
                fn ($query) => $query->orderByDesc('reviews_avg_rating')->orderByDesc('id')
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'title',
                fn ($query) => $query->orderBy('title')->orderBy('id')
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'newest',
                fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id')
            )
            ->paginate(10)
            ->withQueryString(); // ページネーションリンクに検索条件を引き継ぐ

        $genres = Genre::all();

        return view('books.index', compact('books', 'genres'));
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

        $book = DB::transaction(function () use ($validated, $request) {
            $book = Book::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'author_name' => $validated['author'] ?? null,
                'isbn' => $validated['isbn'],
                'published_date' => $validated['published_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            $book->genres()->sync($validated['genres']);

            return $book;
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍更新処理
     * 認証＋認可必須（作成者のみ）。
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $book) {
            $book->update([
                'title' => $validated['title'],
                'author_name' => $validated['author'] ?? null,
                'isbn' => $validated['isbn'],
                'published_date' => $validated['published_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            $book->genres()->sync($validated['genres']);
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * 書籍詳細画面
     * 公開ページ（ゲスト可）。
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->latest(),
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
        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
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

    /**
     * ★ISBN検索（GET /books/isbn/{isbn}）
     * 書籍登録フォームから非同期（Ajax）で呼び出される。
     * 見つかった場合はフォームに埋め込める書籍情報をJSONで返す。
     * 見つからない・不正な場合は {"error": "..."} を返す（books/create.blade.php のJSが
     * data.error を見て判定する作りになっているため、キー名は "message" ではなく "error"）。
     */
    public function searchByIsbn(string $isbn, GoogleBooksService $googleBooksService): JsonResponse
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return response()->json([
                'error' => 'ISBNは13桁の数字で指定してください。',
            ], 422);
        }

        $result = $googleBooksService->searchByIsbn($isbn);

        if ($result === null) {
            return response()->json([
                'error' => '指定されたISBNの書籍が見つかりませんでした。',
            ], 404);
        }

        return response()->json($result);
    }
}
