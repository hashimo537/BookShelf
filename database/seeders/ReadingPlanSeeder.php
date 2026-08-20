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
     * PM確認済み（2026-08-20回答）の仕様を反映：
     * - リマインダーは3日前・当日・3日後の3タイミング
     * - 自動失効は「期限当日」のバッチ実行で発生する（3日後や4日後ではない）
     * - 期限切れになった後も、3日後リマインダーは送られる（レコードも残る）
     * - 期限切れの計画は、期日を未来日に変更すれば進行中に復帰できる
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

        if (! $mainUser || ! $otherUser) {
            $this->command?->warn('ReadingPlanSeeder: UserSeederが先に実行されている必要があります。スキップします。');

            return;
        }

        $books = Book::inRandomOrder()->take(9)->get();

        if ($books->count() < 9) {
            $this->command?->warn('ReadingPlanSeeder: 書籍が9冊未満のため、一部シナリオで書籍が重複します。');
        }

        // ① リマインダー「3日前」が発火する進行中の計画
        $this->createPlan($mainUser, $books[0], Carbon::today()->addDays(3), ReadingPlanStatus::InProgress);

        // ② リマインダー「当日」が発火し、同じバッチ実行内で自動失効もする進行中の計画
        //   （PM確認済み：当日リマインダーと自動失効は同じバッチ実行の中で起きる）
        $this->createPlan($mainUser, $books[1], Carbon::today(), ReadingPlanStatus::InProgress);

        // ③ 既に期限切れだが、期日が「3日後リマインダー」の条件に一致する計画
        //   （PM確認済み：期限切れになった後も3日後リマインダーは送られる）
        $this->createPlan($mainUser, $books[2], Carbon::today()->subDays(3), ReadingPlanStatus::Expired);

        // ④ 期日を大幅に過ぎているのに進行中のまま（バッチが数日動かなかった想定の
        //   キャッチアップシナリオ）。当日ちょうどでなくても失効することの確認用
        $this->createPlan($mainUser, $books[3], Carbon::today()->subDays(10), ReadingPlanStatus::InProgress);

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
        $this->createPlan($mainUser, $books[6], Carbon::today()->subDays(20), ReadingPlanStatus::Expired);

        // ⑧ 完了済みの書籍(⑥と同じ)に対する新規の進行中計画
        //   （PM確認済み：完了済みなら同一書籍でも新規作成できることの確認用）
        $this->createPlan($mainUser, $books[5], Carbon::today()->addDays(14), ReadingPlanStatus::InProgress);

        // ⑨ 完了済みだが、期日が「当日リマインダー」の条件と偶然重なる計画
        //   （ステータスが進行中でなければ、期日が一致していても通知・失効されないことの確認用）
        $this->createPlan(
            $mainUser,
            $books[8],
            Carbon::today(),
            ReadingPlanStatus::Completed,
            completedAt: Carbon::today()->subDay()
        );

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
