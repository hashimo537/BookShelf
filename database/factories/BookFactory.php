<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author_name' => fake()->name(),
            'isbn' => fake()->unique()->numerify('#############'), // 13桁
            'published_date' => fake()->date(),
            'description' => fake()->paragraph(),
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=' . fake()->numberBetween(1, 999),
        ];
    }
}
