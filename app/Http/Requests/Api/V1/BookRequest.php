<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    /**
     * 書籍一覧APIは認証不要（API仕様書のとおり）。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],

            // PM確認済み：ジャンルは「ジャンルID（数値）」で受け取る。
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],

            // 並び順：新しい順（デフォルト）／古い順／タイトル順
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'title'])],

            'page' => ['nullable', 'integer', 'min:1'],

            // per_pageは「デフォルト値＋上限クランプ」方式（PM確認済み）。
            // ここでは「整数であること・1以上であること」だけを検証し、
            // 100件を超える指定はエラーにせず、コントローラ側で min($perPage, 100) として丸める。
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',

            'genre_id.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_id.exists' => '指定されたジャンルは存在しません。',

            'sort.in' => '並び順は newest（新しい順）・oldest（古い順）・title（タイトル順）のいずれかを指定してください。',

            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上を指定してください。',

            'per_page.integer' => '表示件数は整数で指定してください。',
            'per_page.min' => '表示件数は1以上を指定してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'genre_id' => 'ジャンル',
            'sort' => '並び順',
            'page' => 'ページ番号',
            'per_page' => '表示件数',
        ];
    }
}
