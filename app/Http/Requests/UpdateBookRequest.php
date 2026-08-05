<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    /**
     * 更新できるのは登録者本人のみ（BookPolicy::update と同じ判定）。
     */
    public function authorize(): bool
    {
        $book = $this->route('book');

        return $book !== null && $this->user()?->id === $book->user_id;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'digits:13',
                Rule::unique('books', 'isbn')->ignore($this->route('book')),
            ],
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
            // 未入力
            'title.required' => 'タイトルは必須です。',
            'author.required' => '著者名は必須です。',
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
