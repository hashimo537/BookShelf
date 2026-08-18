<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * ★Sanctum導入後：認証はルート側の auth:sanctum ミドルウェアで担保済みのため、
     * ここでは常にtrue（未認証リクエストはこのFormRequestに到達する前に弾かれる）。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ★Sanctum導入前は user_id をリクエストボディで受け取っていたが、
            // 認証済みユーザーのIDを自動的に使うよう変更したため、このルールは削除した。
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
            'title.required' => 'タイトルは必須です。',
            'author_name.required' => '著者名は必須です。',
            'isbn.required' => 'ISBNは必須です。',
            'published_date.required' => '出版日は必須です。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',

            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',

            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'published_date.before_or_equal' => '出版日には今日以前の日付を入力してください。',

            'image_url.url' => '有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',

            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author_name.max' => '著者名は255文字以内で入力してください。',
            'description.max' => '説明は1000文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
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
