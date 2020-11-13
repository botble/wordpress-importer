<?php

return [
    'name'                        => 'Wordpress Importer',
    'copy_images'                 => 'Copy Images',
    'copy_images_description'     => 'Featured images for posts and pages will be copied over to your storage. If you select "No" the image references will remain the same and no images will be copied.',
    'copy_categories'             => 'Copy Categories',
    'copy_categories_description' => 'Categories for posts will be copied. If you uncheck you can select default category for all imported posts.',
    'options'                     => 'Options',
    'select_default_category'     => 'Select default category',
    'loading'                     => 'Loading...',
    'upload_xml'                  => 'Upload your Wordpress XML export file below and click on Import.',
    'timeout_description'         => 'When copying over posts and images from your site it may take awhile if you have a lot of data, set this to as high as you would like to prevent the script from timing out.',
    'max_timeout'                 => 'Max Timeout in Seconds',
    'wp_xml_file'                 => 'Wordpress XML file',
    'wp_xml_file_description'     => 'Inside of your Wordpress Admin you can chose to export data by visiting Tools->Export.',
    'import'                      => 'Import',
    'xml_file_required'           => 'Please specify a Wordpress XML file that you would like to upload.',
    'invalid_xml_file'            => 'Invalid file type. Please make sure you are uploading a Wordpress XML export file.',
    'import_success'              => 'Imported :posts posts, :pages pages, :categories categories, :tags tags, and :users users successfully!',
];
