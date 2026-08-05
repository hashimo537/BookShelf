<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            // user_id と review_id の組み合わせを複合主キーとする（id カラムは持たない）
            $table->primary(['user_id', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
