<?php

return [
    'name' => 'WordPress导入器',
    'description' => '此工具可帮助将WordPress博客数据（如文章、标签、用户和分类）导入您的系统。如需导入WooCommerce数据，请参阅以下说明。',
    'copy_images' => '复制图片',
    'copy_images_description' => '文章和页面的特色图片将被复制到您的存储中。如果选择"否"，图片引用将保持不变，不会复制任何图片。',
    'copy_categories' => '复制分类',
    'copy_categories_description' => '文章的分类将被复制。如果取消勾选，您可以为所有导入的文章选择默认分类。',
    'options' => '选项',
    'select_default_category' => '选择默认分类',
    'loading' => '加载中...',
    'upload_xml' => '在下方上传您的WordPress XML导出文件，然后点击导入。',
    'timeout_description' => '从您的网站复制文章和图片时，如果数据量较大可能需要一些时间。请将此值设置得尽可能高，以防止脚本超时。',
    'max_timeout' => '最大超时时间（秒）',
    'wp_xml_file' => 'WordPress XML文件',
    'wp_xml_file_description' => '在WordPress管理后台中，您可以通过访问工具->导出来选择导出数据。',
    'import' => '导入',
    'xml_file_required' => '请指定要上传的WordPress XML文件。',
    'invalid_xml_file' => '无效的文件类型。请确保您上传的是WordPress XML导出文件。',
    'import_success' => '成功导入 :posts 篇文章、:pages 个页面、:categories 个分类、:tags 个标签和 :users 个用户！',
    'load_seo_meta' => '加载SEO元数据',
    'load_seo_meta_description' => '从WordPress Yoast SEO插件加载SEO元数据（用于文章和页面）。',
    'load_more' => '加载更多',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce产品',
            'description' => '从CSV文件导入WooCommerce产品数据。',
            'export_instruction' => '要获取WooCommerce产品CSV文件，请导航到WordPress管理仪表板，然后转到产品->所有产品。点击左上角的"导出"按钮。选择要导出的产品数据并选择适当的文件格式（CSV）。最后，点击"生成CSV"按钮下载导出的文件。',
        ],
    ],
];
