<?php
// routes/api.php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController as ApiBookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ★AP06: ログイン・ログアウト
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // AP01・AP02: 書籍一覧・詳細（認証不要）
    Route::get('/books', [ApiBookController::class, 'index'])->name('books.index');
    Route::get('/books/{book}', [ApiBookController::class, 'show'])->name('books.show');

    // ★AP03〜AP05: 書籍登録・更新・削除（Sanctumトークン必須）
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/books', [ApiBookController::class, 'store'])->name('books.store');
        Route::put('/books/{book}', [ApiBookController::class, 'update'])->name('books.update');
        Route::delete('/books/{book}', [ApiBookController::class, 'destroy'])->name('books.destroy');
    });
});
