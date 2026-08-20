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
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * AP01: 書籍一覧API（GET /api/v1/books）
     * 認証不要。
     */
    public function index(BookRequest $request): JsonResponse
    {
        $validated = $request->validated();
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
                $query->whereHas('genres', fn ($q) => $q->where('genres.id', $genreId));
            })
            ->when(
                ($validated['sort'] ?? 'newest') === 'oldest',
                fn ($query) => $query->orderBy('created_at')->orderBy('id')
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'title',
                fn ($query) => $query->orderBy('title')->orderBy('id')
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'newest',
                fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id')
            )
            ->paginate($perPage);

        return BookResource::collection($books)->response();
    }

    /**
     * AP02: 書籍詳細API（GET /api/v1/books/{book}）
     * 認証不要。
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user']);

        return new BookResource($book);
    }

    /**
     * ★AP03: 書籍登録API（POST /api/v1/books）
     * Sanctumトークン必須（routes/api.phpのauth:sanctumミドルウェアで担保）。
     * 登録者IDはリクエストボディではなく、認証済みユーザーから自動的に取得する。
     */
    /**
     * ★AP03: 書籍登録API（POST /api/v1/books）
     * Sanctumトークン必須（routes/api.phpのauth:sanctumミドルウェアで担保）。
     * 登録者IDはリクエストボディではなく、認証済みユーザーから自動的に取得する。
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $book = DB::transaction(function () use ($validated, $request) {
            $book = Book::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'author_name' => $validated['author_name'],
                'isbn' => $validated['isbn'],
                'published_date' => $validated['published_date'],
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            $book->genres()->sync($validated['genres']);

            return $book;
        });

        $book->load('genres');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * ★AP04: 書籍更新API（PUT /api/v1/books/{book}）
     * Sanctumトークン必須 + 登録者本人のみ（UpdateBookRequest::authorize()で担保）。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $book) {
            $book->update([
                'title' => $validated['title'],
                'author_name' => $validated['author_name'],
                'isbn' => $validated['isbn'],
                'published_date' => $validated['published_date'],
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            $book->genres()->sync($validated['genres']);
        });

        $book->load('genres');

        return new BookResource($book);
    }

    /**
     * ★AP05: 書籍削除API（DELETE /api/v1/books/{book}）
     * Sanctumトークン必須 + 登録者本人のみ（BookPolicyで担保）。
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
