<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $readingPlan = $this->route('reading_plan');

        return $readingPlan !== null
            && $this->user()?->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * 仕様確認：重複制御（同一user×bookでin_progress状態の他計画が存在しないこと）は、
     * 「期限切れからの復帰時」に限定せず、編集時は常にチェックする（自身は除外）。
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $readingPlan = $this->route('reading_plan');

            if ($readingPlan === null) {
                return;
            }

            $hasOtherInProgressPlan = ReadingPlan::where('user_id', $this->user()->id)
                ->where('book_id', $readingPlan->book_id)
                ->where('id', '!=', $readingPlan->id)
                ->where('status', ReadingPlanStatus::InProgress)
                ->exists();

            if ($hasOtherInProgressPlan) {
                $validator->errors()->add('target_date', 'この書籍は既に進行中の読書計画が存在します。');
            }
        });
    }

    public function messages(): array
    {
        return [
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'target_date' => '期日',
        ];
    }
}
