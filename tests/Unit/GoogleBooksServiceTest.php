<?php

namespace Tests\Unit;

use App\Services\GoogleBooksService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class GoogleBooksServiceTest extends TestCase
{
    #[TestDox('ISBNが見つかった場合、書籍情報を正しく整形して返す')]
    public function test_search_by_isbn_returns_formatted_book_data_when_found(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '吾輩は猫である',
                            'authors' => ['夏目漱石'],
                            'description' => '猫の視点から人間社会を描いた物語。',
                            'publishedDate' => '1905-01-01',
                            'imageLinks' => [
                                'thumbnail' => 'https://example.com/thumbnail.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GoogleBooksService();
        $result = $service->searchByIsbn('9784101010014');

        $this->assertNotNull($result);
        $this->assertEquals('吾輩は猫である', $result['title']);
        $this->assertEquals('夏目漱石', $result['author']);
        $this->assertEquals('1905-01-01', $result['published_date']);
        $this->assertEquals('https://example.com/thumbnail.jpg', $result['image_url']);
    }

    #[TestDox('複数著者の場合、著者名が読点区切りで連結される')]
    public function test_search_by_isbn_joins_multiple_authors_with_japanese_comma(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    ['volumeInfo' => ['title' => '共著本', 'authors' => ['著者A', '著者B']]],
                ],
            ], 200),
        ]);

        $service = new GoogleBooksService();
        $result = $service->searchByIsbn('1111111111111');

        $this->assertEquals('著者A、著者B', $result['author']);
    }

    #[TestDox('publishedDateが年のみの場合、01-01を補って正規化する')]
    public function test_search_by_isbn_normalizes_year_only_published_date(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    ['volumeInfo' => ['title' => '年のみの本', 'publishedDate' => '2020']],
                ],
            ], 200),
        ]);

        $service = new GoogleBooksService();
        $result = $service->searchByIsbn('2222222222222');

        $this->assertEquals('2020-01-01', $result['published_date']);
    }

    #[TestDox('publishedDateが年月のみの場合、01日を補って正規化する')]
    public function test_search_by_isbn_normalizes_year_month_published_date(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    ['volumeInfo' => ['title' => '年月のみの本', 'publishedDate' => '2020-05']],
                ],
            ], 200),
        ]);

        $service = new GoogleBooksService();
        $result = $service->searchByIsbn('3333333333333');

        $this->assertEquals('2020-05-01', $result['published_date']);
    }

    #[TestDox('該当する書籍が見つからない場合はnullを返す')]
    public function test_search_by_isbn_returns_null_when_not_found(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['totalItems' => 0], 200),
        ]);

        $service = new GoogleBooksService();
        $result = $service->searchByIsbn('9999999999999');

        $this->assertNull($result);
    }

    #[TestDox('APIがエラーを返した場合はnullを返す（例外を投げない）')]
    public function test_search_by_isbn_returns_null_when_api_request_fails(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $service = new GoogleBooksService();
        $result = $service->searchByIsbn('4444444444444');

        $this->assertNull($result);
    }
}
