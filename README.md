# BookShelf 書籍レビュー・読書計画管理アプリ

## 概要

書籍の登録・レビュー・お気に入り・ランキング表示に加え、読書計画の管理とリマインダー通知までを一貫してサポートするWebアプリケーションです。Laravel 10で構築し、Webブラウザからの操作に加え、Sanctumトークン認証による公開APIも提供しています。

### 実装した主な機能

- 書籍のCRUD、キーワード検索・ジャンル絞り込み・並び替え、ISBN検索（Google Books API連携）
- レビュー投稿・いいね、お気に入り登録、評価ランキング表示
- ジャンル管理
- マイ読書レポート（総レビュー数・評価分布・高評価書籍TOP5・ジャンル別評価傾向TOP5）
- 読書計画のCRUD、状態管理（進行中・完了・期限切れ）、日次バッチによるリマインダー通知（3日前・当日・3日後）と自動失効
- 会員登録・ログイン（Fortify）
- 公開API（書籍CRUD、Sanctumトークン認証）

## 作成者

- 橋元　麻由

## 開発環境URL

```
http://localhost
```

## 目次

- [使用技術](#使用技術)
- [環境構築](#環境構築)
- [シーディングされるアカウント](#シーディングされるアカウント)
- [ER図](#er図)
- [画面一覧](#画面一覧)
- [API一覧](#api一覧)
- [バッチ処理](#バッチ処理)
- [テスト](#テスト)

---

## 使用技術

| 分類 | 技術 |
|---|---|
| 言語 | PHP 8.2 |
| フレームワーク | Laravel 10 |
| 認証（Web） | Laravel Fortify（会員登録・ログイン・ログアウトのみ有効） |
| 認証（API） | Laravel Sanctum（トークン認証） |
| データベース | MySQL |
| 実行環境 | Laravel Sail（Docker） |
| フロントエンド | Blade + Tailwind CSS |
| テスト | PHPUnit（`#[TestDox]`属性で日本語テスト名を出力） |
| コード整形 | Laravel Pint |
| 外部API連携 | Google Books API（ISBN検索） |
| 開発支援 | barryvdh/laravel-ide-helper |

---

## 環境構築

### 前提

- Docker Desktop がインストール済みであること

### 手順

# 1. リポジトリをクローン
git clone git@github.com:your-account/BookShelf.git
cd BookShelf

# 2. .envファイルを作成
cp .env.example .env

# 3. Composerの依存パッケージをインストール
#   （ローカルにPHP/Composerが無い場合はDockerイメージ経由でインストール）
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs

# 4. Sailを起動
./vendor/bin/sail up -d

# 5. アプリケーションキーを生成
sail artisan key:generate

# 6. マイグレーション＋シーディングを実行
sail artisan migrate --seed

# 7. Google Books APIキーを.envに設定（ISBN検索機能を使う場合）
#   GOOGLE_BOOKS_API_KEY=（発行したキー）
#   取得方法: Google Cloud ConsoleでBooks APIを有効化し、APIキーを発行する

# 8. フロントエンド資材をビルド
sail npm install
sail npm run build


ブラウザで `http://localhost` にアクセスして動作確認してください。

### 日次バッチの動作確認（任意）

読書計画のリマインダー通知・自動失効バッチは、通常`cron`経由で毎日自動実行されますが、手動でも実行できます。

sail artisan reading-plans:process


---

## シーディングされるアカウント

`sail artisan migrate --seed` 実行後、以下のユーザーが登録されます（パスワードは全員共通）。

| 名前 | メールアドレス | パスワード |
|---|---|---|
| 山田太郎 | yamada@example.com | password |
| 鈴木花子 | suzuki@example.com | password |
| 田中一郎 | tanaka@example.com | password |
| 佐藤美咲 | sato@example.com | password |
| 高橋健太 | takahashi@example.com | password |

読書計画のダミーデータ（リマインダー発火・自動失効・認可判定の各シナリオ）は主に**山田太郎**に集約されています。

---

## ER図

erDiagram
    users ||--o{ books : "登録する"
    users ||--o{ genres : "登録する"
    users ||--o{ reviews : "投稿する"
    users ||--o{ reading_plans : "作成する"
    users ||--o{ favorites : "book_idを介して書籍をお気に入り登録"
    users ||--o{ likes : "review_idを介してレビューにいいね"
    users ||--o{ notifications : "受け取る"

    books ||--o{ reviews : "レビューされる"
    books ||--o{ reading_plans : "計画される"
    books }o--o{ genres : "book_genre"
    books ||--o{ favorites : "お気に入り登録される"

    reviews ||--o{ likes : "いいねされる"

    users {
        bigint id PK
        string name
        string email UK
        string password
    }
    books {
        bigint id PK
        bigint user_id FK
        string title
        string author_name
        string isbn UK
        date published_date
        text description
        string image_url
    }
    genres {
        bigint id PK
        bigint user_id FK
        string name UK
    }
    reviews {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        tinyint rating
        text comment
    }
    book_genre {
        bigint book_id PK_FK
        bigint genre_id PK_FK
    }
    favorites {
        bigint user_id PK_FK
        bigint book_id PK_FK
    }
    likes {
        bigint user_id PK_FK
        bigint review_id PK_FK
    }
    reading_plans {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        date target_date
        timestamp completed_at
        string status
    }
    notifications {
        uuid id PK
        string type
        string notifiable_type
        bigint notifiable_id FK
        json data
        timestamp read_at
    }


### 補足

- `book_genre`・`favorites`・`likes`は、`id`カラムを持たない純粋な中間テーブル（複合主キー）です
- `books.user_id`・`genres.user_id`・`reviews.user_id`・`reading_plans.user_id`は、いずれも`users`削除時に`cascadeOnDelete`されます
- `book_genre.genre_id`のみ`restrictOnDelete`（使用中のジャンルは削除不可）
- `notifications`はLaravel標準のデータベース通知機能をそのまま使用しています

---

## 画面一覧

| 画面 | パス | 認証 |
|---|---|---|
| 書籍一覧（トップ） | `/` `/books` | 不要 |
| 書籍詳細 | `/books/{book}` | 不要 |
| 書籍登録・編集 | `/books/create` `/books/{book}/edit` | 必須（編集は所有者のみ） |
| ジャンル一覧・詳細・登録・編集 | `/genres` 他 | 必須（所有者制限なし） |
| レビュー編集 | `/reviews/{review}/edit` | 必須（投稿者のみ） |
| お気に入り一覧 | `/favorites` | 必須 |
| ランキング | `/ranking` | 不要 |
| マイ読書レポート | `/reports` | 必須 |
| 読書計画一覧・作成・編集 | `/reading-plans` 他 | 必須（編集・完了は所有者かつ完了済みでない場合のみ） |
| 通知一覧 | `/notifications` | 必須 |
| 会員登録・ログイン | `/register` `/login` | Fortify提供 |

---

## API一覧

すべて `/api/v1` 配下。JSON形式でレスポンスを返します。

| メソッド | パス | 認証 | 説明 |
|---|---|---|---|
| POST | `/api/v1/login` | 不要 | ログインしてトークンを発行 |
| POST | `/api/v1/logout` | 必須 | 現在のトークンを無効化 |
| GET | `/api/v1/books` | 不要 | 書籍一覧（`keyword`・`genre_id`・`sort`・`page`・`per_page`対応） |
| GET | `/api/v1/books/{book}` | 不要 | 書籍詳細（ジャンル・レビュー含む） |
| POST | `/api/v1/books` | 必須（Sanctum） | 書籍登録 |
| PUT | `/api/v1/books/{book}` | 必須（Sanctum・所有者のみ） | 書籍更新 |
| DELETE | `/api/v1/books/{book}` | 必須（Sanctum・所有者のみ） | 書籍削除 |

**エラーレスポンスの共通フォーマット**

| ステータス | 内容 |
|---|---|
| 401 | `{"message": "認証が必要です。"}` |
| 403 | `{"message": "この操作を行う権限がありません。"}` |
| 404 | `{"message": "指定された書籍が見つかりませんでした。"}` |
| 422 | `{"message": "入力内容に誤りがあります。", "errors": {...}}` |

---

## バッチ処理

読書計画のリマインダー通知（期日3日前・当日・3日後）と、期日から4日以上経過した進行中の計画を自動的に「期限切れ」にする処理を、日次バッチとして実装しています。

# 手動実行
sail artisan reading-plans:process


`app/Console/Kernel.php`の`schedule()`で毎日実行されるよう登録済みです。

# スケジュール登録の確認
sail artisan schedule:list


---

## テスト

# 全テスト実行（日本語のテスト内容を表示）
sail artisan test --testdox

# カバレッジ計測
sail artisan test --coverage
```

現在、単体テスト・機能テストあわせて189件、カバレッジ98.6%です。