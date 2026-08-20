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

    protected $description = '読書計画のリマインダー通知（3日前・当日・3日後）送信と、期限当日の自動失効を行う日次バッチ（毎日20時実行）';

    public function handle(): int
    {
        $today = Carbon::today();

        // PM確認済み（2026-08-20回答）:
        // - 3日前・当日・3日後の3タイミングでリマインダーを送る
        // - 期限当日のバッチ実行で「進行中」→「期限切れ」に変更する
        //   （＝当日リマインダー送信と自動失効は同じバッチ実行の中で起きる）
        // - 期限切れになった後も、3日後リマインダーは送る（レコードも残す）
        $this->sendReminders($today->copy()->addDays(3), 'three_days_before', onlyInProgress: true);
        $this->sendReminders($today->copy(), 'on_due_date', onlyInProgress: true);
        $this->sendReminders($today->copy()->subDays(3), 'three_days_after', onlyInProgress: false);

        $this->expireDuePlans($today);

        return self::SUCCESS;
    }

    /**
     * 指定タイミングの対象計画にリマインダー通知を送る。
     * 同一計画・同一タイミングでの重複送信を防ぐため、
     * 既に同じtimingの通知が送られていないかを確認する。
     *
     * $onlyInProgress = true  : 「進行中」の計画のみ対象（3日前・当日）
     * $onlyInProgress = false : 「完了」以外（進行中・期限切れ）を対象（3日後。
     *                           期限当日に既に「期限切れ」へ変更済みのため）
     */
    private function sendReminders(Carbon $targetDate, string $timing, bool $onlyInProgress): void
    {
        $plans = ReadingPlan::query()
            ->when(
                $onlyInProgress,
                fn ($query) => $query->where('status', ReadingPlanStatus::InProgress),
                fn ($query) => $query->where('status', '!=', ReadingPlanStatus::Completed)
            )
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
     * 期日が今日以前になった「進行中」の計画を「期限切れ」に変更する。
     * PM確認済み：期限当日のバッチ実行で失効させる（3日後や4日後ではない）。
     * target_date <= today としているのは、何らかの理由でバッチが
     * 数日動かなかった場合でも、既に期日を過ぎている計画を確実に拾うため。
     */
    private function expireDuePlans(Carbon $today): void
    {
        $count = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', '<=', $today->toDateString())
            ->update(['status' => ReadingPlanStatus::Expired]);

        $this->info("{$count}件の読書計画を期限切れにしました。");
    }
}
