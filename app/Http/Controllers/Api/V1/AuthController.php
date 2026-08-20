<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ログイン・トークン発行（POST /api/v1/login）
     * 認証不要。
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * ログアウト・トークン無効化（POST /api/v1/logout）
     * 認証必須（auth:sanctum）。
     */
    public function logout(Request $request): JsonResponse
    {
        // 現在使用中のトークンだけを無効化する（他デバイスのトークンは残す）
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
