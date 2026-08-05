<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍の編集・削除は登録者本人のみ許可する。
     * （表示・登録自体は誰でも可なので update/delete のみ定義すればよい）
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
