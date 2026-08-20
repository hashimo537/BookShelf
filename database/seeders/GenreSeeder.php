<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $registeredBy = User::first();

        $genres = [
            '小説',
            'ビジネス',
            '技術書',
            '自己啓発',
            'エッセイ',
            '歴史',
            '科学',
            '芸術',
            '料理',
            '旅行',
        ];

        foreach ($genres as $name) {
            Genre::firstOrCreate(
                ['name' => $name],
                ['user_id' => $registeredBy->id] // ← ここでuser_idを渡している
            );
        }
    }
}
