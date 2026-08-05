<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    /**
     * 更新できるのは投稿者本人のみ（ReviewPolicy::update と同じ判定）。
     */
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review !== null && $this->user()?->id === $review->user_id;
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
