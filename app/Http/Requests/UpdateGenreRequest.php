<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    /**
     * ジャンル編集はログイン済みユーザーなら誰でも可（PM確認済み・所有者制限なし）。
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres', 'name')->ignore($this->route('genre')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
            'name.max' => '255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名は既に使用されています。',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ジャンル名',
        ];
    }
}
