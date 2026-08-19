<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;

class StoreReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                function ($attribute, $value, $fail) {
                    // PM確認済み：同一書籍に「進行中」の計画が既にある場合は新規作成不可。
                    // 完了済み・期限切れの計画がある場合は新規作成を許可する。
                    $hasInProgressPlan = ReadingPlan::where('user_id', $this->user()->id)
                        ->where('book_id', $value)
                        ->where('status', ReadingPlanStatus::InProgress)
                        ->exists();

                    if ($hasInProgressPlan) {
                        $fail('この書籍にはすでに進行中の読書計画があります。');
                    }
                },
            ],
            // 計画なので、今日以降の日付のみ許可する
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。',
            'book_id.exists' => '選択された書籍が存在しません。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日には今日以降の日付を入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'book_id' => '書籍',
            'target_date' => '期日',
        ];
    }
}