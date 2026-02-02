<?php

return [
    'name' => 'WordPress匯入器',
    'description' => '此工具可協助將WordPress部落格資料（如文章、標籤、使用者和分類）匯入您的系統。如需匯入WooCommerce資料，請參閱以下說明。',
    'copy_images' => '複製圖片',
    'copy_images_description' => '文章和頁面的精選圖片將被複製到您的儲存中。如果選擇「否」，圖片參考將保持不變，不會複製任何圖片。',
    'copy_categories' => '複製分類',
    'copy_categories_description' => '文章的分類將被複製。如果取消勾選，您可以為所有匯入的文章選擇預設分類。',
    'options' => '選項',
    'select_default_category' => '選擇預設分類',
    'loading' => '載入中...',
    'upload_xml' => '在下方上傳您的WordPress XML匯出檔案，然後點擊匯入。',
    'timeout_description' => '從您的網站複製文章和圖片時，如果資料量較大可能需要一些時間。請將此值設定得盡可能高，以防止腳本逾時。',
    'max_timeout' => '最大逾時時間（秒）',
    'wp_xml_file' => 'WordPress XML檔案',
    'wp_xml_file_description' => '在WordPress管理後台中，您可以透過造訪工具->匯出來選擇匯出資料。',
    'import' => '匯入',
    'xml_file_required' => '請指定要上傳的WordPress XML檔案。',
    'invalid_xml_file' => '無效的檔案類型。請確保您上傳的是WordPress XML匯出檔案。',
    'import_success' => '成功匯入 :posts 篇文章、:pages 個頁面、:categories 個分類、:tags 個標籤和 :users 個使用者！',
    'load_seo_meta' => '載入SEO中繼資料',
    'load_seo_meta_description' => '從WordPress Yoast SEO外掛載入SEO中繼資料（用於文章和頁面）。',
    'load_more' => '載入更多',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce產品',
            'description' => '從CSV檔案匯入WooCommerce產品資料。',
            'export_instruction' => '要取得WooCommerce產品CSV檔案，請導覽至WordPress管理儀表板，然後前往產品->所有產品。點擊左上角的「匯出」按鈕。選擇要匯出的產品資料並選擇適當的檔案格式（CSV）。最後，點擊「產生CSV」按鈕下載匯出的檔案。',
        ],
    ],
];
