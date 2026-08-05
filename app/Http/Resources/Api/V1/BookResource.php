<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * 一覧・詳細の両方で使い回す。
     * - 一覧（index）: genres, reviews_avg_rating, reviews_count を含む
     * - 詳細（show）  : genres, reviews（投稿者名・評価・コメント・投稿日時）を含む
     * whenLoaded/whenCounted を使っているので、
     * コントローラ側でロードしていない関連は自動的にレスポンスから省かれる。
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author, // Bookモデルのアクセサ（実カラムは author_name）
            'isbn' => $this->isbn,
            'published_date' => $this->published_date?->format('Y-m-d'),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'reviews_avg_rating' => $this->when(
                $this->reviews_avg_rating !== null,
                fn() => round((float) $this->reviews_avg_rating, 2)
            ),
            'reviews_count' => $this->whenCounted('reviews'),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
