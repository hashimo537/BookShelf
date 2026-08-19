<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookIndexRequest extends FormRequest
{
    /**
     * 書籍一覧は公開ページのため誰でも閲覧可。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            // books/index.blade.php のセレクトボックスの選択肢と一致させる
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'rating', 'title'])],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre.integer' => 'ジャンルの指定が正しくありません。',
            'genre.exists' => '指定されたジャンルは存在しません。',
            'sort.in' => '並び順の指定が正しくありません。',
        ];
    }
}
