<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * アプリケーションのデータベースをシードする。
     * 依存関係を考慮し、以下の順番で実行する。
     * 1. UserSeeder
     * 2. GenreSeeder
     * 3. BookSeeder
     * 4. ReviewSeeder
     * 5. FavoriteSeeder
     * 6. ReviewLikeSeeder
     * 7. ReadingPlanSeeder（★応用：Book・Userの投入後に実行する必要がある）
     *
     * `sail artisan db:seed` でまとめて投入できる。
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            ReviewLikeSeeder::class,
            ReadingPlanSeeder::class,
        ]);
    }
}
