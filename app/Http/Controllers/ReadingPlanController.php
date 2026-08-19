<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧（GET /reading-plans）
     * 認証必須。ログインユーザー自身の計画のみを表示し、状態で絞り込める。
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status', '');

        $readingPlans = $request->user()
            ->readingPlans()
            ->with('book')
            ->when($currentStatus !== '', fn($query) => $query->where('status', $currentStatus))
            ->latest('target_date')
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 読書計画作成画面（GET /reading-plans/create）
     */
    public function create(): View
    {
        $books = Book::orderBy('title')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画登録処理（POST /reading-plans）
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    /**
     * 読書計画編集画面（GET /reading-plans/{reading_plan}/edit）
     * 認証＋認可必須（所有者のみ）。期日変更のみを行う画面。
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', ['readingPlan' => $readingPlan]);
    }

    /**
     * 読書計画更新処理（PUT /reading-plans/{reading_plan}）
     * 認証＋認可必須（所有者のみ）。期日変更のみ。
     * 期限切れだった計画の期日を未来日に変更した場合は、進行中に戻す。
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $validated = $request->validated();

        $readingPlan->target_date = $validated['target_date'];

        if ($readingPlan->status === ReadingPlanStatus::Expired) {
            $readingPlan->status = ReadingPlanStatus::InProgress;
        }

        $readingPlan->save();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読了アクション（POST /reading-plans/{reading_plan}/complete）
     * 認証＋認可必須（所有者のみ）。
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読了しました。お疲れさまでした。');
    }

    /**
     * 読書計画削除処理（DELETE /reading-plans/{reading_plan}）
     * 認証＋認可必須（所有者のみ）。
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }
}