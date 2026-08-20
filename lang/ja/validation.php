<?php

// lang/ja/validation.php

return [
    'required' => ':attributeは必須です。',
    'string' => ':attributeは文字列で入力してください。',
    'integer' => ':attributeは整数で入力してください。',
    'array' => ':attributeは配列で入力してください。',
    'date' => ':attributeは正しい日付形式で入力してください。',
    'email' => ':attributeはメールアドレス形式で入力してください。',
    'confirmed' => ':attributeと一致しません。',
    'unique' => 'その:attributeは既に使用されています。',
    'exists' => '選択された:attributeは存在しません。',
    'in' => '選択された:attributeは正しくありません。',
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
        'numeric' => ':attributeは:max以下で指定してください。',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
        'numeric' => ':attributeは:min以上で指定してください。',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Rule Language Lines
    |--------------------------------------------------------------------------
    |
    | Fortifyのパスワードルール（Illuminate\Validation\Rules\Password）用。
    | 今回は「8文字以上」チェックのみ有効化する運用のため、min以外は
    | 万一有効化された場合のフォールバックとして汎用メッセージにしている。
    |
    */
    'password' => [
        'letters' => '有効なパスワードで入力してください。',
        'mixed' => '有効なパスワードで入力してください。',
        'numbers' => '有効なパスワードで入力してください。',
        'symbols' => '有効なパスワードで入力してください。',
        'uncompromised' => '有効なパスワードで入力してください。',
        'min' => ':attributeは:min文字以上で入力してください。',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | 特定のフィールド×ルールの組み合わせだけ、汎用メッセージとは別の
    | 文言を使いたい場合にここへ追加する（例: emailのunique文言）。
    |
    */
    'custom' => [
        'email' => [
            'unique' => 'このメールアドレスは既に使用されております。',
        ],
    ],

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'title' => 'タイトル',
        'description' => '説明',
        'status' => '状態',
        'due_date' => '期限',
        'category_id' => 'カテゴリ',
        'tags' => 'タグ',
        'per_page' => '1ページあたりの件数',
        'page' => 'ページ番号',
        'user_id' => '登録者',
        'keyword' => 'キーワード',
    ],
];
