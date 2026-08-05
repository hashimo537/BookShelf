<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * レビュー投稿処理（POST /books/{book}/reviews）
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $validated = $request->validated();

        $book->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * レビュー編集画面（GET /reviews/{review}/edit）
     * 認証＋認可必須（投稿者のみ）。
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review); // 本人でなければ403

        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    /**
     * レビュー更新処理（PUT /reviews/{review}）
     * 認証＋認可必須（投稿者のみ）。
     */
    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $validated = $request->validated();

        $review->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return redirect()
            ->route('books.show', $review->book)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * レビュー削除処理（DELETE /reviews/{review}）
     * 認証＋認可必須（投稿者のみ）。
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $book = $review->book;
        $review->delete();

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを削除しました。');
    }
}
