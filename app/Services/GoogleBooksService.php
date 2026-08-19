<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBooksService
{
    /**
     * ISBNで書籍を検索し、書籍登録フォームに埋め込める形式で返す。
     * 見つからない場合・APIエラー時はnullを返す（呼び出し側で「見つかりませんでした」を表示する想定）。
     *
     * @return array{title: string, author: string, description: string|null, image_url: string|null, published_date: string|null}|null
     */
    public function searchByIsbn(string $isbn): ?array
    {
        $baseUrl = config('services.google_books.base_url');
        $apiKey = config('services.google_books.key');

        $response = Http::get("{$baseUrl}/volumes", [
            'q' => "isbn:{$isbn}",
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            Log::warning('Google Books API request failed', [
                'isbn' => $isbn,
                'status' => $response->status(),
            ]);

            return null;
        }

        $items = $response->json('items');

        if (empty($items)) {
            return null;
        }

        $volumeInfo = $items[0]['volumeInfo'] ?? [];

        return [
            'title' => $volumeInfo['title'] ?? '',
            'author' => implode('、', $volumeInfo['authors'] ?? []),
            'description' => $volumeInfo['description'] ?? null,
            'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
            'published_date' => $this->normalizePublishedDate($volumeInfo['publishedDate'] ?? null),
        ];
    }

    /**
     * Google Books APIのpublishedDateは "2020" や "2020-01" のような
     * 不完全な形式で返ってくることがあるため、DBのdate型に保存できる
     * "Y-m-d" 形式に正規化する（欠けている月日は01で補う）。
     */
    private function normalizePublishedDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return match (strlen($date)) {
            4 => "{$date}-01-01",       // "2020"
            7 => "{$date}-01",          // "2020-01"
            default => $date,           // "2020-01-01" 等はそのまま
        };
    }
}
