<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookController extends Controller
{
    /**
     * AP01: 書籍一覧API（GET /api/v1/books）
     * 認証不要。キーワード検索・ジャンル絞り込み・ページネーションに対応。
     */
    public function index(BookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // per_page は「デフォルト20・上限100でクランプ」方式（PM確認済み）。
        // バリデーションでは1以上の整数であることのみを検証しており、
        // 100を超える指定はエラーにせずここで丸め込む。
        $perPage = min($validated['per_page'] ?? 20, 100);

        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author_name', 'like', "%{$keyword}%");
                });
            })
            ->when($validated['genre_id'] ?? null, function ($query, $genreId) {
                $query->whereHas('genres', fn($q) => $q->where('genres.id', $genreId));
            })
            ->when(
                ($validated['sort'] ?? 'newest') === 'oldest',
                fn($query) => $query->oldest()
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'title',
                fn($query) => $query->orderBy('title')
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'newest',
                fn($query) => $query->latest()
            )
            ->paginate($perPage);

        return BookResource::collection($books)->response();
    }

    /**
     * AP02: 書籍詳細API（GET /api/v1/books/{book}）
     * 認証不要。ジャンル情報とレビュー（投稿者名・評価・コメント・投稿日時）を含める。
     * 存在しないIDの場合は、ルートモデルバインディングが自動的に404を返す。
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user']);

        return new BookResource($book);
    }

    /**
     * AP03: 書籍登録API（POST /api/v1/books）
     * 基礎段階では認証不要。登録者IDはリクエストボディで受け取る。
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'author_name' => $validated['author_name'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);
        $book->load('genres');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED); // 201
    }

    /**
     * AP04: 書籍更新API（PUT /api/v1/books/{book}）
     * 基礎段階では認証不要。存在しないIDの場合はルートモデルバインディングが404を返す。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $validated = $request->validated();

        $book->update([
            'title' => $validated['title'],
            'author_name' => $validated['author_name'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);
        $book->load('genres');

        return new BookResource($book);
    }

    /**
     * AP05: 書籍削除API（DELETE /api/v1/books/{book}）
     * 関連データ（レビュー・お気に入り・ジャンル紐付け）は
     * すべて cascadeOnDelete のため、書籍削除と同時に自動的に削除される。
     */
    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent(); // 204
    }
}
