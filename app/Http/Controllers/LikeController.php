<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * レビューへのいいね登録・解除のトグル（POST /reviews/{review}/like）
     * 認証必須。自分のレビューへのいいねも許可する（PM確認済み）。
     */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        $request->user()->likedReviews()->toggle($review->id);

        return back();
    }
}
