<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * 読書計画の編集・読了操作は「所有者本人」かつ「完了済みでない」場合のみ許可する。
     * PM確認済み：完了済みの計画は編集不可。期限切れの計画は期日変更可（＝許可対象）。
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 削除は所有者本人であればステータスを問わず許可する。
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
