<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知一覧（GET /notifications）
     * 認証必須。ログインユーザー自身の通知を新着順に表示する。
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にする（POST /notifications/{notification}/read）
     * 認証必須。$request->user()->notifications() 経由で取得するため、
     * 他人の通知IDを指定してもこのユーザーの通知しか見つからず、404になる。
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', '既読にしました。');
    }
}