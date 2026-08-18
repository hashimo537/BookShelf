<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        // api/* 配下は、Postmanなどで Accept: application/json ヘッダーが
        // 付いていなくても必ずJSONを返すようにする。
        if ($request->is('api/*')) {

            // ★AP06: トークンが無い・無効・期限切れ（auth:sanctumミドルウェアが投げる）
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => '認証が必要です。',
                ], 401);
            }

            // ★AP06: トークンは有効だが、他人の書籍を更新・削除しようとした（BookPolicyが投げる）
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => 'この操作を行う権限がありません。',
                ], 403);
            }

            // AP02/AP04: 存在しないIDが指定された場合（ルートモデルバインディング失敗）
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'message' => '指定された書籍が見つかりませんでした。',
                ], 404);
            }

            // 存在しないURL・存在しないHTTPメソッドへのアクセス
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => '指定されたURLは見つかりませんでした。',
                ], 404);
            }

            // AP03/AP04: バリデーションエラー（FormRequestのrules()に違反した場合）
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => '入力内容に誤りがあります。',
                    'errors' => $e->errors(),
                ], 422);
            }
        }

        return parent::render($request, $e);
    }
}
