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

> ⚠️ 以下の手順は採点環境と完全に一致させる必要があります。異なる手順（`laravel.build`の使用、最新版Laravelの使用等）で構築した場合、動作しない可能性があります。

### 1. Laravelプロジェクトの作成（Laravel 10.x）

`curl -s "https://laravel.build/..."`は最新版のLaravelをインストールしてしまうため使用しません。以下でLaravel 10.xを明示的に指定して作成します。

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 bookshelf-app
```

### 2. Laravel Sailのインストール

```bash
cd bookshelf-app

docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer require laravel/sail --dev

docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    php artisan sail:install --with=mysql
```

> **M1/M2/M3 Mac（Apple Silicon）をお使いの方**：`sail up -d`実行時に`no matching manifest for linux/arm64/v8`エラーが出た場合は、`compose.yaml`の`mysql`サービスに`platform: 'linux/amd64'`を追加してください。

### 3. `.env`ファイルの設定

データベース接続情報が以下と一致していることを確認してください。

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

**重要**：`DB_HOST`は`localhost`や`127.0.0.1`ではなく、Dockerコンテナ名の`mysql`を指定します。

このタイミングで、Google Books API連携（ISBN検索機能）用のキーも追記しておきます。

```
GOOGLE_BOOKS_API_KEY=（発行したキー）
GOOGLE_BOOKS_API_BASE_URL=https://www.googleapis.com/books/v1
```

（キーの取得方法は「使用技術・外部API連携」セクションを参照）

### 4. フロントエンドのセットアップ（Vite & Tailwind CSS）

```bash
# 1. NPM依存パッケージのインストール
# Sailコンテナが起動していることを確認（未起動なら ./vendor/bin/sail up -d）
sail npm install

# 2. Alpine.jsのインストール
sail npm install alpinejs

# 3. Tailwind CSSと @tailwindcss/forms プラグインのインストール
sail npm install -D tailwindcss@^3.4.0 @tailwindcss/forms postcss autoprefixer

# 4. 設定ファイルの生成
sail npx tailwindcss init -p
```

`tailwind.config.js`を以下の内容で上書きします。

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [forms],
};
```

**resourcesファイルの入れ替え**：`coachtech-prepared-file/Preparedblade-mockcase-BookShelf`リポジトリの**Basicブランチ**の`resources`ファイルで、本プロジェクトの`resources`ディレクトリを置き換えてください。

```bash
sail npm run dev
```

開発中はこのコマンドを常に実行した状態にしておいてください。

### 5. phpMyAdminの追加

`compose.yaml`の`mysql`サービスの後に以下を追加します。

```yaml
    phpmyadmin:
        image: 'phpmyadmin:latest'
        ports:
            - '${FORWARD_PHPMYADMIN_PORT:-8080}:80'
        environment:
            PMA_HOST: mysql
            PMA_USER: '${DB_USERNAME}'
            PMA_PASSWORD: '${DB_PASSWORD}'
        networks:
            - sail
        depends_on:
            - mysql
```

### 6. Sailの起動とエイリアス設定

```bash
./vendor/bin/sail up -d

echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc
exec $SHELL
```

### 7. アプリケーションキーの生成

```bash
sail artisan key:generate
```

### 7-2. 追加パッケージのインストール（本プロジェクト固有）

Sanctum（APIトークン認証）とカラム変更マイグレーション用の`doctrine/dbal`を追加します。

```bash
sail composer require laravel/sanctum
sail artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

sail composer require doctrine/dbal

# 通知機能（読書計画のリマインダー）用テーブル
sail artisan notifications:table
```

### 8. データベースのマイグレーションと初期データ投入

```bash
sail artisan migrate --seed
```

既存のデータベースをリセットしたい場合：

```bash
sail artisan migrate:fresh --seed
```

> ⚠️ シーディング直後は通知（`/notifications`）が0件です。読書計画のリマインダーは日次バッチ（`reading-plans:process`）実行時に生成されるため、動作確認する場合は以下も実行してください。
> ```bash
> sail artisan reading-plans:process
> ```

**日本語化（バリデーション・認証メッセージ）について（基本）**：`config/app.php`の`locale`を`ja`にし、`lang/ja/`にメッセージファイルを**手動配置**しています。

> 🚨 **`laravel-lang/lang`などの`laravel-lang/*`系パッケージ（`composer require laravel-lang/...`）は導入していません。** 同系パッケージは2026年5月のサプライチェーン攻撃でマルウェア配布に悪用された経緯があるため、意図的に使用を避けています。

### ★ 9. 応用機能用Bladeテンプレートのインポートと環境調整

基本機能の実装完了後、応用機能の画面に対応するBladeテンプレートを取得し、環境を拡張します。

1. **Bladeテンプレートの差し替え**：`coachtech-prepared-file/Preparedblade-mockcase-BookShelf`リポジトリの**Advancedブランチ**から`resources`ファイルを再度インポートし、プロジェクトの`resources`ディレクトリを置き換える
2. **応用版データモデル変更の適用**：マイグレーションを追加し、`sail artisan migrate:fresh --seed`で再構築する（`reading_plans`テーブルの新規作成、`books.author_name`/`books.published_date`のnullable化など）


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

mermaid
---
title: "BookShelf"
---
erDiagram
    users ||--o{ books : ""
    users ||--o{ favorites : ""
    books ||--o{ favorites : ""

    users {
        bigint id PK 
        varchar name "名前"
        varchar email "メールアドレス UNIQUE"
        timestamp email_verified_at "NULL許可"
        varchar password "パスワード"
        varchar remember_token "NULL許可"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }

    books {
        bigint id PK 
        bigint user_id FK "登録者ID"
        varchar title "タイトル"
        varchar author_name "著者名 NULL許可"
        varchar isbn "ISBN UNIQUE"
        date published_date "出版日 NULL許可"
        text description "説明 NULL許可"
        varchar image_url "画像URL NULL許可"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }

    favorites {
        bigint user_id PK,FK "登録者ID"
        bigint book_id PK,FK "本ID"
    }

    users ||--o{ likes : ""
    reviews ||--o{ likes : ""

    likes {
        bigint user_id PK,FK "登録者ID"
        bigint review_id PK,FK "レビューID"
    }

    users ||--o{ reviews : ""
    books ||--o{ reviews : ""

    reviews {
        bigint id PK "ID"
        bigint user_id FK "登録者.id"
        bigint book_id FK "本ID"
        int rating "評価（1〜5）"
        text comment "コメント"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }

    books ||--o{ book_genre : ""
    genres ||--o{ book_genre : ""

    genres {
        bigint id PK "ID"
        varchar name "ジャンル名 UNIQUE"
        bigint user_id FK "登録者.id"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }

    book_genre {
        bigint book_id PK,FK ""
        bigint genre_id PK,FK ""
    }

    users ||--o{ reading_plans : ""
    books ||--o{ reading_plans : ""

    reading_plans {
        bigint id PK "ID"
        bigint user_id FK "登録者.id"
        bigint book_id FK "本ID"
        date target_date "期日"
        timestamp completed_at "完了日時 NULL許可"
        varchar status "状態（進行中/完了/期限切れ）"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }

    users ||--o{ notifications : ""

    notifications {
        char id PK "UUID"
        varchar type "通知クラス名"
        varchar notifiable_type "通知先モデル種別"
        bigint notifiable_id FK "通知先ID（users.id）"
        json data "通知内容（title/body/timing等）"
        timestamp read_at "既読日時 NULL許可"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
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

bash
# スケジュール登録の確認
sail artisan schedule:list


### ⚠️ シーディング直後は通知が0件です

`ReadingPlanSeeder`が投入するのは「読書計画（`reading_plans`）」のデータのみで、「通知（`notifications`）」はこのバッチコマンドを実行して初めて生成されます。そのため、`migrate:fresh --seed`を実行した直後は、`/notifications`（通知一覧画面）が空の状態になります。

動作確認する際は、シーディング後に**手動で一度バッチを実行してください**。

sail artisan migrate:fresh --seed
sail artisan reading-plans:process

本番運用では、上記の`Kernel.php`のスケジュール設定により毎日自動実行されるため、この手動実行は開発・動作確認時のみ必要な手順です。

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