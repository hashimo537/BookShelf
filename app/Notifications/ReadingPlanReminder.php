<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    /**
     * @param  'three_days_before'|'on_due_date'|'three_days_after'  $timing
     */
    public function __construct(
        public ReadingPlan $readingPlan,
        public string $timing,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'reading_plan_id' => $this->readingPlan->id,
            'timing' => $this->timing,
            'title' => $this->buildTitle(),
            'body' => $this->buildBody(),
        ];
    }

    private function buildTitle(): string
    {
        return match ($this->timing) {
            'three_days_before' => '読書計画の期日が近づいています',
            'on_due_date' => '本日が読書計画の期日です',
            'three_days_after' => '読書計画の期日を過ぎています',
        };
    }

    private function buildBody(): string
    {
        $title = $this->readingPlan->book->title;
        $targetDate = $this->readingPlan->target_date->format('Y-m-d');

        return match ($this->timing) {
            'three_days_before' => "「{$title}」の読了予定日（{$targetDate}）まであと3日です。",
            'on_due_date' => "「{$title}」の読了予定日（{$targetDate}）は本日です。",
            'three_days_after' => "「{$title}」の読了予定日（{$targetDate}）を3日過ぎています。期日を変更するか読了にしてください。",
        };
    }
}
