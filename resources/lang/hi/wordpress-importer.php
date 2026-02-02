<?php

return [
    'name' => 'वर्डप्रेस आयातक',
    'description' => 'This tool facilitates the import of WordPress blog data such as posts, tags, users, and categories into your system. If you need to import WooCommerce data, please refer to the section below for instructions.',
    'copy_images' => 'Copy Images',
    'copy_images_description' => 'Featured images for posts and pages will be copied over to your storage. If you select "No" the image references will remain the same and no images will be copied.',
    'copy_categories' => 'Copy Categories',
    'copy_categories_description' => 'Categories for posts will be copied. If you uncheck you can select default category for all imported posts.',
    'options' => 'विकल्प',
    'select_default_category' => 'Select default category',
    'loading' => 'लोड हो रहा है...',
    'upload_xml' => 'Upload your WordPress XML export file below and click on Import.',
    'timeout_description' => 'When copying over posts and images from your site it may take awhile if you have a lot of data, set this to as high as you would like to prevent the script from timing out.',
    'max_timeout' => 'Max Timeout in Seconds',
    'wp_xml_file' => 'WordPress XML file',
    'wp_xml_file_description' => 'Inside of your WordPress Admin you can chose to export data by visiting Tools->Export.',
    'import' => 'आयात करें',
    'xml_file_required' => 'Please specify a WordPress XML file that you would like to upload.',
    'invalid_xml_file' => 'Invalid file type. Please make sure you are uploading a WordPress XML export file.',
    'import_success' => 'Imported :posts posts, :pages pages, :categories categories, :tags tags, and :users users successfully!',
    'load_seo_meta' => 'Load SEO Meta',
    'load_seo_meta_description' => 'Load SEO Meta(for Post and Page) from WordPress Yoast SEO plugin.',
    'load_more' => 'और लोड करें',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce Products',
            'description' => 'Import WooCommerce Products data from CSV file.',
            'export_instruction' => 'To obtain your WooCommerce Products CSV file, navigate to the WordPress admin dashboard, then go to Products -> All Products. Click on the "Export" button located at the top left corner. Select the product data you wish to export and choose the appropriate file format (CSV). Finally, click on the "Generate CSV" button to download the exported file.',
        ],
    ],
];
