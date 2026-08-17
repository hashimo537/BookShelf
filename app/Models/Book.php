<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author_name',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * Blade側で使用する `author` アクセサ（実カラムは author_name）
     */
    protected function author(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->author_name,
        );
    }

    /**
     * 書籍の登録者
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 書籍に紐づくジャンル
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre');
    }

    /**
     * 書籍に投稿されたレビュー
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍をお気に入り登録しているユーザー
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
    /**
     * この書籍のレビュー平均評価を計算する（小数第2位まで）。
     * レビューが1件も無い場合はnullを返す。
     */
    public function averageRating(): ?float
    {
        $average = $this->reviews()->avg('rating');

        return $average !== null ? round((float) $average, 2) : null;
    }
}
