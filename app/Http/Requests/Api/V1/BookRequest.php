<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'title', 'rating'])],
            'page' => ['nullable', 'integer', 'min:1'],
            // 仕様書確認：表示件数は100以下を「バリデーションエラー」として弾く
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.max' => 'キーワードは255文字以内で入力してください。',

            'genre_id.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_id.exists' => '指定されたジャンルは存在しません。',

            'sort.in' => '並び順の指定が正しくありません。',

            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上を指定してください。',

            'per_page.integer' => '表示件数は整数で指定してください。',
            'per_page.min' => '表示件数は1以上を指定してください。',
            'per_page.max' => '表示件数は100以下を指定してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'genre_id' => 'ジャンルID',
            'sort' => '並び順',
            'page' => 'ページ番号',
            'per_page' => '表示件数',
        ];
    }
}
