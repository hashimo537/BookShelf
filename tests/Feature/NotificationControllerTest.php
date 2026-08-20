<?php

namespace Tests\Feature;

use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('未ログイン状態では通知一覧にアクセスできない')]
    public function test_guest_cannot_view_notifications(): void
    {
        $response = $this->get(route('notifications.index'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーは自分の通知一覧を表示できる')]
    public function test_authenticated_user_can_view_own_notifications(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        $user->notify(new ReadingPlanReminder($plan, 'on_due_date'));

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
    }

    #[TestDox('通知一覧には自分の通知のみが表示され、他人の通知は表示されない')]
    public function test_index_only_shows_the_authenticated_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myPlan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        $user->notify(new ReadingPlanReminder($myPlan, 'on_due_date'));

        $othersPlan = ReadingPlan::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->notify(new ReadingPlanReminder($othersPlan, 'on_due_date'));

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertViewHas('notifications', fn($notifications) => $notifications->count() === 1);
    }

    #[TestDox('通知を既読にできる')]
    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        $user->notify(new ReadingPlanReminder($plan, 'on_due_date'));
        $notification = $user->notifications()->first();

        $response = $this->actingAs($user)->post(route('notifications.read', $notification->id));

        $response->assertRedirect(route('notifications.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[TestDox('他人の通知IDを指定して既読にしようとすると404になる')]
    public function test_user_cannot_mark_others_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->notify(new ReadingPlanReminder($plan, 'on_due_date'));
        $notification = $otherUser->notifications()->first();

        $response = $this->actingAs($user)->post(route('notifications.read', $notification->id));

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }
}