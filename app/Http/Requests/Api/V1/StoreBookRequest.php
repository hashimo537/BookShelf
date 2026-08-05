<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * 基礎段階では認証不要（API仕様書のとおり）。
     * 応用段階（★AP06）で Sanctum のトークン認証をミドルウェアに追加した際は、
     * ここで Auth::check() を見る必要はない（ミドルウェア側で弾かれるため）。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 認証が無い基礎段階では、リクエストボディで登録者IDを受け取り、
            // 実在するユーザーかどうかだけを検証する（なりすまし防止は応用段階のSanctumで対応）。
            'user_id' => ['required', 'integer', 'exists:users,id'],

            'title' => ['required', 'string', 'max:255'],
            'author_name' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date', 'before_or_equal:today'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    public function messages(): array
    {
        return [
            // 登録者ID
            'user_id.required' => '登録者IDは必須です。',
            'user_id.integer' => '登録者IDは整数で指定してください。',
            'user_id.exists' => '指定された登録者IDのユーザーが存在しません。',

            // 未入力
            'title.required' => 'タイトルは必須です。',
            'author_name.required' => '著者名は必須です。',
            'isbn.required' => 'ISBNは必須です。',
            'published_date.required' => '出版日は必須です。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',

            // ISBN形式・一意性
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',

            // 出版日
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'published_date.before_or_equal' => '出版日には今日以前の日付を入力してください。',

            // 画像URL
            'image_url.url' => '有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',

            // 文字数超過
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author_name.max' => '著者名は255文字以内で入力してください。',
            'description.max' => '説明は1000文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => '登録者ID',
            'title' => 'タイトル',
            'author_name' => '著者名',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genres' => 'ジャンル',
        ];
    }
}
