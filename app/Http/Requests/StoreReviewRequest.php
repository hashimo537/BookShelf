<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * レビュー投稿はログイン済みユーザーなら誰でも可（auth ミドルウェアで担保済み）。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は1〜5の整数で入力してください。',
            'rating.between' => '評価は1〜5の整数で入力してください。',
            'comment.required' => 'コメントは必須です。',
            'comment.max' => '1000文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'rating' => '評価',
            'comment' => 'コメント',
        ];
    }
}
