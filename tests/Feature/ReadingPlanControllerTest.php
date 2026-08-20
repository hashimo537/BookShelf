<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ReadingPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 一覧
    // ---------------------------------------------------------------

    #[TestDox('未ログイン状態では読書計画一覧にアクセスできない')]
    public function test_guest_cannot_view_index(): void
    {
        $response = $this->get(route('reading-plans.index'));

        $response->assertRedirect(route('login'));
    }

    #[TestDox('ログイン済みユーザーは自分の読書計画一覧を表示できる')]
    public function test_authenticated_user_can_view_own_plans(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reading-plans.index'));

        $response->assertOk();
        $response->assertViewIs('reading-plans.index');
    }

    #[TestDox('一覧には自分の読書計画のみが表示され、他人の計画は表示されない')]
    public function test_index_only_shows_the_authenticated_users_plans(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myBook = Book::factory()->create(['title' => '自分の計画の本']);
        ReadingPlan::factory()->create(['user_id' => $user->id, 'book_id' => $myBook->id]);

        $othersBook = Book::factory()->create(['title' => '他人の計画の本']);
        ReadingPlan::factory()->create(['user_id' => $otherUser->id, 'book_id' => $othersBook->id]);

        $response = $this->actingAs($user)->get(route('reading-plans.index'));

        $response->assertSee('自分の計画の本');
        $response->assertDontSee('他人の計画の本');
    }

    #[TestDox('statusパラメータで状態による絞り込みができる')]
    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        $inProgressBook = Book::factory()->create(['title' => '進行中の本']);
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $inProgressBook->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $completedBook = Book::factory()->create(['title' => '完了済みの本']);
        ReadingPlan::factory()->completed()->create([
            'user_id' => $user->id,
            'book_id' => $completedBook->id,
        ]);

        $response = $this->actingAs($user)->get(route('reading-plans.index', ['status' => 'completed']));

        $response->assertSee('完了済みの本');
        $response->assertDontSee('進行中の本');
    }

    // ---------------------------------------------------------------
    // 登録
    // ---------------------------------------------------------------

    #[TestDox('ログイン済みユーザーは読書計画を登録できる')]
    public function test_authenticated_user_can_store_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'in_progress',
        ]);
        $response->assertRedirect(route('reading-plans.index'));
    }

    #[TestDox('書籍が未選択の場合は登録に失敗する')]
    public function test_store_fails_when_book_is_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('book_id');
    }

    #[TestDox('期日が過去日の場合は登録に失敗する')]
    public function test_store_fails_when_target_date_is_in_the_past(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->subDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('target_date');
    }

    // ---------------------------------------------------------------
    // 編集・更新（期日変更のみ）
    // ---------------------------------------------------------------

    #[TestDox('所有者本人は読書計画編集画面を表示できる')]
    public function test_owner_can_view_edit_page(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reading-plans.edit', $plan));

        $response->assertOk();
        $response->assertViewIs('reading-plans.edit');
    }

    #[TestDox('所有者本人でなければ読書計画編集画面にアクセスすると403になる')]
    public function test_non_owner_cannot_view_edit_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get(route('reading-plans.edit', $plan));

        $response->assertForbidden();
    }

    #[TestDox('所有者本人は期日を変更できる')]
    public function test_owner_can_update_target_date(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        $newDate = now()->addDays(30)->format('Y-m-d');

        $response = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => $newDate,
        ]);

        $response->assertRedirect(route('reading-plans.index'));
        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'target_date' => $newDate,
        ]);
    }

    #[TestDox('期限切れの計画を未来日に変更すると進行中に戻る')]
    public function test_updating_expired_plan_to_future_date_resets_status_to_in_progress(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'status' => 'in_progress',
        ]);
    }

    #[TestDox('所有者本人でなければ読書計画を更新しようとすると403になる')]
    public function test_non_owner_cannot_update_plan(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertForbidden();
    }

    // ---------------------------------------------------------------
    // 読了アクション
    // ---------------------------------------------------------------

    #[TestDox('所有者本人は読書計画を読了にできる')]
    public function test_owner_can_complete_reading_plan(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('reading-plans.complete', $plan));

        $response->assertRedirect(route('reading-plans.index'));
        $plan->refresh();
        $this->assertEquals(ReadingPlanStatus::Completed, $plan->status);
        $this->assertNotNull($plan->completed_at);
    }

    #[TestDox('所有者本人でなければ読了操作をしようとすると403になる')]
    public function test_non_owner_cannot_complete_plan(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->post(route('reading-plans.complete', $plan));

        $response->assertForbidden();
    }

    // ---------------------------------------------------------------
    // 削除
    // ---------------------------------------------------------------

    #[TestDox('所有者本人は読書計画を削除できる')]
    public function test_owner_can_delete_plan(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('reading-plans.destroy', $plan));

        $response->assertRedirect(route('reading-plans.index'));
        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    #[TestDox('所有者本人でなければ読書計画を削除しようとすると403になる')]
    public function test_non_owner_cannot_delete_plan(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->delete(route('reading-plans.destroy', $plan));

        $response->assertForbidden();
        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }

    #[TestDox('未ログイン状態では読書計画を削除できない')]
    public function test_guest_cannot_delete_plan(): void
    {
        $plan = ReadingPlan::factory()->create();

        $response = $this->delete(route('reading-plans.destroy', $plan));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }
    // ---------------------------------------------------------------
    // PM確認済み：同一書籍の重複制御（進行中のみ禁止）
    // ---------------------------------------------------------------

    #[TestDox('同一書籍に進行中の計画が既にある場合は新規作成できない')]
    public function test_store_fails_when_book_already_has_in_progress_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('book_id');
        $this->assertDatabaseCount('reading_plans', 1);
    }

    #[TestDox('同一書籍の既存計画が完了済みであれば新規作成できる')]
    public function test_store_succeeds_when_existing_plan_for_book_is_completed(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->completed()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reading_plans', 2);
    }

    #[TestDox('同一書籍の既存計画が期限切れであれば新規作成できる')]
    public function test_store_succeeds_when_existing_plan_for_book_is_expired(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->expired()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reading_plans', 2);
    }

    // ---------------------------------------------------------------
    // PM確認済み：完了済みは編集不可、期限切れは編集可
    // ---------------------------------------------------------------

    #[TestDox('完了済みの計画は編集画面にアクセスすると403になる')]
    public function test_completed_plan_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reading-plans.edit', $plan));

        $response->assertForbidden();
    }

    #[TestDox('完了済みの計画は更新しようとすると403になる')]
    public function test_completed_plan_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertForbidden();
    }

    #[TestDox('期限切れの計画は編集画面にアクセスできる')]
    public function test_expired_plan_can_be_edited(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reading-plans.edit', $plan));

        $response->assertOk();
    }

    #[TestDox('期限切れの計画は期日を変更できる')]
    public function test_expired_plan_can_be_updated(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('reading-plans.index'));
        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'status' => 'in_progress', // 未来日への変更で進行中に復帰
        ]);
    }
}
