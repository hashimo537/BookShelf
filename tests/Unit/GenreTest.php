<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Genreモデルのbooks()リレーション（多対多）で、紐付けた書籍が正しく取得できる')]
    public function test_books_relation_returns_attached_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(3)->create();

        foreach ($books as $book) {
            $genre->books()->attach($book);
        }

        $this->assertCount(3, $genre->books);
    }
}
