<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ProcessReadingPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('期日3日前の進行中計画にリマインダー通知が送られる')]
    public function test_sends_three_days_before_reminder(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertEquals('three_days_before', $notification->data['timing']);
    }

    // 差し替え対象①：以前は「同じバッチ実行内で期限切れになる」ことを検証していたが、
    // 期日当日はまだ「進行中」のままが正しい仕様。テスト名・アサーションを修正。
    #[TestDox('期日当日の進行中計画にリマインダー通知が送られるが、ステータスは進行中のまま維持される')]
    public function test_sends_on_due_date_reminder_and_keeps_status_in_progress(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertEquals('on_due_date', $notification->data['timing']);

        // 採点フィードバック反映：期日当日は失効させない
        $this->assertEquals(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    #[TestDox('既に期限切れの計画にも、期日3日後のタイミングでリマインダー通知が送られる')]
    public function test_sends_three_days_after_reminder_even_for_already_expired_plan(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Expired, // PM確認済み：期限切れ後も3日後通知は送る
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertEquals('three_days_after', $notification->data['timing']);
    }

    #[TestDox('同じ計画・同じタイミングでは重複して通知が送られない')]
    public function test_does_not_send_duplicate_reminder_for_same_timing(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');
        $this->artisan('reading-plans:process'); // 2回実行

        $this->assertDatabaseCount('notifications', 1);
    }

    // 差し替え対象②：以前は「期日当日ちょうどで期限切れになる」ことを検証していたが、
    // 正しくは「期日当日は進行中のまま、翌日から期限切れになる」。
    #[TestDox('期日当日の進行中の計画はまだ期限切れにならない')]
    public function test_does_not_expire_plan_exactly_on_due_date(): void
    {
        $plan = ReadingPlan::factory()->create([
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertEquals(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    // 新規追加：期日の翌日から期限切れになることを確認する
    #[TestDox('期日の翌日になった進行中の計画は期限切れになる')]
    public function test_expires_plan_the_day_after_due_date(): void
    {
        $plan = ReadingPlan::factory()->create([
            'target_date' => Carbon::yesterday(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertEquals(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }


    #[TestDox('期日を大幅に過ぎている進行中の計画も、まとめて期限切れになる（バッチのキャッチアップ）')]
    public function test_expires_plans_that_are_significantly_overdue(): void
    {
        $plan = ReadingPlan::factory()->create([
            'target_date' => Carbon::today()->subDays(10),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertEquals(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    

    #[TestDox('期日がまだ先の進行中の計画は期限切れにならない')]
    public function test_does_not_expire_plans_with_future_target_date(): void
    {
        $plan = ReadingPlan::factory()->create([
            'target_date' => Carbon::today()->addDay(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertEquals(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    #[TestDox('完了済みの計画は期日を過ぎていても失効しない')]
    public function test_does_not_expire_completed_plans(): void
    {
        $plan = ReadingPlan::factory()->completed()->create([
            'target_date' => Carbon::today()->subDays(10),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertEquals(ReadingPlanStatus::Completed, $plan->fresh()->status);
    }

    #[TestDox('完了済みの計画は、期日がリマインダー条件と重なっていても通知が送られない')]
    public function test_does_not_send_reminder_for_completed_plan_even_if_date_matches(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today(), // on_due_dateの条件と一致
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::yesterday(),
        ]);

        $this->artisan('reading-plans:process');

        $this->assertDatabaseCount('notifications', 0);
    }

    #[TestDox('期日が近くない計画にはリマインダーが送られない')]
    public function test_does_not_send_reminder_for_unrelated_dates(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today()->addDays(10),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertDatabaseCount('notifications', 0);
    }
}
