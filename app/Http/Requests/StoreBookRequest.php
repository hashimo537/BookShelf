<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * 書籍登録はログイン済みユーザーなら誰でも可（auth ミドルウェアで担保済み）。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // ★応用：ISBN検索での自動補完を想定し、著者名は任意入力に変更
            'author' => ['nullable', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13', 'unique:books,isbn'],
            // ★応用：出版日が不明なケースを想定し、任意入力に変更
            'published_date' => ['nullable', 'date', 'before_or_equal:today'],
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
            'isbn.required' => 'ISBNは必須です。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',

            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',

            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'published_date.before_or_equal' => '出版日には今日以前の日付を入力してください。',

            'image_url.url' => '有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',

            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'description.max' => '説明は1000文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'author' => '著者名',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genres' => 'ジャンル',
        ];
    }
}
