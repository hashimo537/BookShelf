<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 採点フィードバック反映：authorは必須のままが正しい仕様だったため、NOT NULLに戻す。
     * published_dateは引き続きnullableのまま変更しない。
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('author')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('author')->nullable()->change();
        });
    }
};