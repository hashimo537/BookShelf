<?php
// routes/web.php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| トップページ（＝書籍一覧）
|--------------------------------------------------------------------------
| GET / と GET /books は同じ一覧を表示するため、
| home は BookController@index にそのまま委譲する。
*/
Route::get('/', [BookController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| 公開ページ（ログイン不要）
|--------------------------------------------------------------------------
| 注意: GET /books/{book}（books.show）はワイルドカードなので、
| /books/create のような固定文字列のルートと衝突しないよう、
| 必ずファイルの最後の方に置くこと。
*/
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

/*
|--------------------------------------------------------------------------
| 未ログイン専用（会員登録・ログイン・ログアウト）
|--------------------------------------------------------------------------
| GET/POST /register, GET/POST /login, POST /logout は
| Fortify（config/fortify.php の 'views' => true & features）が
| 自動的にルート登録してくれるため、ここでは定義しない。
*/

/*
|--------------------------------------------------------------------------
| ログイン必須
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ★ ISBN検索
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.isbn');

    // 書籍登録・編集・削除（index・show は公開ページとして定義済みのため除外）
    // books.create（GET /books/create）はここで登録される。
    // 下の books.show（GET /books/{book}）より必ず先に登録されている必要がある。
    Route::resource('books', BookController::class)->except(['index', 'show']);

    // レビュー投稿・編集・削除
    // Bladeが route('reviews.store', $book) / route('reviews.edit', $review) のように
    // 常に 'reviews.*' という名前を期待しているため、Route::resource()->shallow() ではなく
    // 手動でルート名を揃える。
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り登録・解除（トグル）。Bladeは route('favorites.toggle', $book) を使用。
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // レビューへのいいね（トグル）
    Route::post('/reviews/{review}/like', [LikeController::class, 'toggle'])->name('reviews.like');

    // ジャンル一覧・詳細・登録・編集・削除（フルCRUD）
    // ※ 同一の Route::resource() 呼び出し内では Laravel が
    //   index → create → store → show → edit → update → destroy の順で
    //   自動登録するため、genres.create と genres.show（ワイルドカード）は衝突しない。
    Route::resource('genres', GenreController::class);

    // ★ マイ読書レポート
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

/*
|--------------------------------------------------------------------------
| 公開ページ（ログイン不要）／ワイルドカードルート
|--------------------------------------------------------------------------
| GET /books/{book} は「books/」に続く1セグメントすべてにマッチしてしまうため、
| books.create 等の固定文字列ルートより後ろに置く。
*/
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
