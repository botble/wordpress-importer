<?php

return [
    'name' => 'WordPressインポーター',
    'description' => 'このツールは、投稿、タグ、ユーザー、カテゴリなどのWordPressブログデータをシステムにインポートするのを容易にします。WooCommerceデータをインポートする必要がある場合は、以下のセクションを参照してください。',
    'copy_images' => '画像をコピー',
    'copy_images_description' => '投稿とページのアイキャッチ画像がストレージにコピーされます。「いいえ」を選択すると、画像の参照はそのまま残り、画像はコピーされません。',
    'copy_categories' => 'カテゴリをコピー',
    'copy_categories_description' => '投稿のカテゴリがコピーされます。チェックを外すと、インポートされたすべての投稿にデフォルトカテゴリを選択できます。',
    'options' => 'オプション',
    'select_default_category' => 'デフォルトカテゴリを選択',
    'loading' => '読み込み中...',
    'upload_xml' => '以下にWordPress XMLエクスポートファイルをアップロードし、インポートをクリックしてください。',
    'timeout_description' => 'サイトから投稿と画像をコピーする際、データが多い場合は時間がかかることがあります。スクリプトのタイムアウトを防ぐために、この値をできるだけ高く設定してください。',
    'max_timeout' => '最大タイムアウト（秒）',
    'wp_xml_file' => 'WordPress XMLファイル',
    'wp_xml_file_description' => 'WordPress管理画面内で、ツール->エクスポートにアクセスしてデータをエクスポートできます。',
    'import' => 'インポート',
    'xml_file_required' => 'アップロードするWordPress XMLファイルを指定してください。',
    'invalid_xml_file' => '無効なファイルタイプです。WordPress XMLエクスポートファイルをアップロードしていることを確認してください。',
    'import_success' => ':posts件の投稿、:pages件のページ、:categories件のカテゴリ、:tags件のタグ、:users人のユーザーを正常にインポートしました！',
    'load_seo_meta' => 'SEOメタを読み込む',
    'load_seo_meta_description' => 'WordPress Yoast SEOプラグインからSEOメタ（投稿とページ用）を読み込みます。',
    'load_more' => 'もっと読み込む',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce商品',
            'description' => 'CSVファイルからWooCommerce商品データをインポートします。',
            'export_instruction' => 'WooCommerce商品CSVファイルを取得するには、WordPress管理ダッシュボードに移動し、商品->すべての商品に移動します。左上にある「エクスポート」ボタンをクリックします。エクスポートする商品データを選択し、適切なファイル形式（CSV）を選択します。最後に、「CSVを生成」ボタンをクリックしてエクスポートファイルをダウンロードします。',
        ],
    ],
];
