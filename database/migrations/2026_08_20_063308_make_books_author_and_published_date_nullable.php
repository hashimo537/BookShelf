<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ★応用：ISBN検索での自動補完を想定し、著者名・出版日を任意入力に変更する。
     * （title・isbn・genresは書籍データとして最低限必要な情報のため必須のまま）
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('author_name')->nullable()->change();
            $table->date('published_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('author_name')->nullable(false)->change();
            $table->date('published_date')->nullable(false)->change();
        });
    }
};
