<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * ★応用：読書計画機能の各種挙動（リマインダー発火/未発火、自動失効、
     * 認可判定）を採点時に網羅的に確認できるダミーデータを投入する。
     *
     * 日付はすべて Carbon::today() を起点とした相対指定にしているため、
     * 採点日が変わっても同じシナリオが再現される。
     *
     * 動作確認の効率のため、主要シナリオは1ユーザー（山田太郎）に集約し、
     * 認可判定の確認用にのみ別ユーザーのデータを1件用意する。
     */
    public function run(): void
    {
        $mainUser = User::where('email', 'yamada@example.com')->first();
        $otherUser = User::where('email', 'suzuki@example.com')->first();

        if (!$mainUser || !$otherUser) {
            $this->command?->warn('ReadingPlanSeeder: UserSeederが先に実行されている必要があります。スキップします。');

            return;
        }

        $books = Book::inRandomOrder()->take(8)->get();

        if ($books->count() < 8) {
            $this->command?->warn('ReadingPlanSeeder: 書籍が8冊未満のため、一部シナリオで書籍が重複します。');
        }

        // ① リマインダー「3日前」が発火する進行中の計画
        $this->createPlan($mainUser, $books[0], Carbon::today()->addDays(3), ReadingPlanStatus::InProgress);

        // ② リマインダー「当日」が発火する進行中の計画
        $this->createPlan($mainUser, $books[1], Carbon::today(), ReadingPlanStatus::InProgress);

        // ③ リマインダー「3日後」が発火する進行中の計画
        $this->createPlan($mainUser, $books[2], Carbon::today()->subDays(3), ReadingPlanStatus::InProgress);

        // ④ 自動失効バッチの対象になる進行中の計画（期日から4日経過＝失効条件を満たす）
        $this->createPlan($mainUser, $books[3], Carbon::today()->subDays(4), ReadingPlanStatus::InProgress);

        // ⑤ どの通知条件にも合致しない進行中の計画（未発火であることの確認用）
        $this->createPlan($mainUser, $books[4], Carbon::today()->addDays(10), ReadingPlanStatus::InProgress);

        // ⑥ 完了済みの計画（バッチの通知・失効いずれの対象外にもなることの確認用）
        $this->createPlan(
            $mainUser,
            $books[5],
            Carbon::today()->subDays(5),
            ReadingPlanStatus::Completed,
            completedAt: Carbon::today()->subDays(6)
        );

        // ⑦ 既に期限切れの計画（編集画面で期日変更→進行中に復帰できることの確認用）
        $this->createPlan($mainUser, $books[6], Carbon::today()->subDays(10), ReadingPlanStatus::Expired);

        // ⑧ 完了済みの書籍(⑥と同じ)に対する新規の進行中計画
        //   （PM確認済み：完了済みなら同一書籍でも新規作成できることの確認用）
        $this->createPlan($mainUser, $books[5], Carbon::today()->addDays(14), ReadingPlanStatus::InProgress);

        // --- 認可判定の確認用：別ユーザーのデータ ---
        // 他人の計画を編集・削除・読了しようとすると403になることの確認に使う。
        $this->createPlan($otherUser, $books[7], Carbon::today()->addDays(5), ReadingPlanStatus::InProgress);
    }

    private function createPlan(
        User $user,
        Book $book,
        Carbon $targetDate,
        ReadingPlanStatus $status,
        ?Carbon $completedAt = null,
    ): ReadingPlan {
        return ReadingPlan::firstOrCreate(
            [
                'user_id' => $user->id,
                'book_id' => $book->id,
                'target_date' => $targetDate->toDateString(),
            ],
            [
                'status' => $status,
                'completed_at' => $completedAt,
            ]
        );
    }
}