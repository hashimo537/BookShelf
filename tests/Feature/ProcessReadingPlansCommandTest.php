<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
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
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => ReadingPlanReminder::class,
        ]);
        $notification = $user->fresh()->notifications()->first();
        $this->assertEquals('three_days_before', $notification->data['timing']);
    }

    #[TestDox('期日当日の進行中計画にリマインダー通知が送られる')]
    public function test_sends_on_due_date_reminder(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $notification = $user->fresh()->notifications()->first();
        $this->assertEquals('on_due_date', $notification->data['timing']);
    }

    #[TestDox('期日3日後の進行中計画にリマインダー通知が送られる')]
    public function test_sends_three_days_after_reminder(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::InProgress,
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
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');
        $this->artisan('reading-plans:process'); // 2回実行

        $this->assertDatabaseCount('notifications', 1);
    }

    #[TestDox('期日から4日以上経過した進行中の計画は期限切れになる')]
    public function test_expires_plans_overdue_by_four_days_or_more(): void
    {
        $plan = ReadingPlan::factory()->create([
            'target_date' => Carbon::today()->subDays(4),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading-plans:process');

        $this->assertEquals(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    #[TestDox('期日から3日しか経過していない進行中の計画はまだ失効しない')]
    public function test_does_not_expire_plans_overdue_by_only_three_days(): void
    {
        $plan = ReadingPlan::factory()->create([
            'target_date' => Carbon::today()->subDays(3),
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