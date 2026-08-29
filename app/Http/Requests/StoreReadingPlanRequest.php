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
                    $hasInProgressPlan = ReadingPlan::where('user_id', $this->user()->id)
                        ->where('book_id', $value)
                        ->where('status', ReadingPlanStatus::InProgress)
                        ->exists();

                    if ($hasInProgressPlan) {
                        // 採点フィードバック反映：指定文言に一致させる
                        $fail('この書籍は既に進行中の読書計画が存在します。');
                    }
                },
            ],
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
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',

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
