<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * 所有者本人のみ更新可（ReadingPlanPolicy::updateと同じ判定）。
     */
    public function authorize(): bool
    {
        $readingPlan = $this->route('reading_plan');

        return $readingPlan !== null && $this->user()?->id === $readingPlan->user_id;
    }

    public function rules(): array
    {
        return [
            // 編集画面は期日変更のみを許可する（書籍・ステータスはここでは変更しない）
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日には今日以降の日付を入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'target_date' => '期日',
        ];
    }
}