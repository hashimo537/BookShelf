<?php
// routes/api.php

use App\Http\Controllers\Api\V1\BookController as ApiBookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 公開API（v1）
|--------------------------------------------------------------------------
| index / show / store / update / destroy のみを使用するため apiResource を使用。
| （create・edit はフォーム表示用のため API では不要）
*/
Route::prefix('v1')->group(function () {
    Route::apiResource('books', ApiBookController::class);
});
