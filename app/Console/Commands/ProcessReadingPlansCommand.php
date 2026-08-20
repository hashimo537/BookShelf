<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessReadingPlansCommand extends Command
{
    protected $signature = 'reading-plans:process';

    protected $description = '読書計画のリマインダー通知（3日前・当日・3日後）送信と、期限切れ計画の自動失効を行う日次バッチ';

    public function handle(): int
    {
        $today = Carbon::today();

        $this->sendReminders($today, $today->copy()->addDays(3), 'three_days_before');
        $this->sendReminders($today, $today->copy(), 'on_due_date');
        $this->sendReminders($today, $today->copy()->subDays(3), 'three_days_after');
        $this->expireOverduePlans($today);

        return self::SUCCESS;
    }

    /**
     * 指定タイミングの対象計画にリマインダー通知を送る。
     * 同一計画・同一タイミングでの重複送信を防ぐため、
     * 既に同じtimingの通知が送られていないかを確認する。
     */
    private function sendReminders(Carbon $today, Carbon $targetDate, string $timing): void
    {
        $plans = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', $targetDate->toDateString())
            ->with(['user', 'book'])
            ->get();

        $plans->each(function (ReadingPlan $plan) use ($timing) {
            $alreadySent = $plan->user->notifications()
                ->where('type', ReadingPlanReminder::class)
                ->where('data->reading_plan_id', $plan->id)
                ->where('data->timing', $timing)
                ->exists();

            if ($alreadySent) {
                return;
            }

            $plan->user->notify(new ReadingPlanReminder($plan, $timing));
        });

        $this->info("[{$timing}] {$plans->count()}件の計画を処理しました。");
    }

    /**
     * 期日から4日以上経過した進行中の計画を「期限切れ」に変更する。
     * （3日後リマインダーを送った翌日以降を失効の基準にしている）
     */
    private function expireOverduePlans(Carbon $today): void
    {
        $threshold = $today->copy()->subDays(4);

        $count = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', '<=', $threshold->toDateString())
            ->update(['status' => ReadingPlanStatus::Expired]);

        $this->info("{$count}件の読書計画を期限切れにしました。");
    }
}
