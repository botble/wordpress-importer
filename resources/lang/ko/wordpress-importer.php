<?php

return [
    'name' => 'WordPress 가져오기',
    'description' => '이 도구는 게시물, 태그, 사용자, 카테고리와 같은 WordPress 블로그 데이터를 시스템으로 가져오는 것을 용이하게 합니다. WooCommerce 데이터를 가져와야 하는 경우 아래 섹션을 참조하세요.',
    'copy_images' => '이미지 복사',
    'copy_images_description' => '게시물과 페이지의 대표 이미지가 저장소로 복사됩니다. "아니오"를 선택하면 이미지 참조는 그대로 유지되며 이미지가 복사되지 않습니다.',
    'copy_categories' => '카테고리 복사',
    'copy_categories_description' => '게시물의 카테고리가 복사됩니다. 선택을 해제하면 가져온 모든 게시물에 대한 기본 카테고리를 선택할 수 있습니다.',
    'options' => '옵션',
    'select_default_category' => '기본 카테고리 선택',
    'loading' => '로딩 중...',
    'upload_xml' => '아래에 WordPress XML 내보내기 파일을 업로드하고 가져오기를 클릭하세요.',
    'timeout_description' => '사이트에서 게시물과 이미지를 복사할 때 데이터가 많으면 시간이 걸릴 수 있습니다. 스크립트 시간 초과를 방지하려면 이 값을 가능한 높게 설정하세요.',
    'max_timeout' => '최대 시간 제한(초)',
    'wp_xml_file' => 'WordPress XML 파일',
    'wp_xml_file_description' => 'WordPress 관리자 내에서 도구->내보내기를 방문하여 데이터를 내보낼 수 있습니다.',
    'import' => '가져오기',
    'xml_file_required' => '업로드할 WordPress XML 파일을 지정하세요.',
    'invalid_xml_file' => '잘못된 파일 유형입니다. WordPress XML 내보내기 파일을 업로드하고 있는지 확인하세요.',
    'import_success' => ':posts개의 게시물, :pages개의 페이지, :categories개의 카테고리, :tags개의 태그, :users명의 사용자를 성공적으로 가져왔습니다!',
    'load_seo_meta' => 'SEO 메타 로드',
    'load_seo_meta_description' => 'WordPress Yoast SEO 플러그인에서 SEO 메타(게시물 및 페이지용)를 로드합니다.',
    'load_more' => '더 보기',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce 제품',
            'description' => 'CSV 파일에서 WooCommerce 제품 데이터를 가져옵니다.',
            'export_instruction' => 'WooCommerce 제품 CSV 파일을 얻으려면 WordPress 관리 대시보드로 이동한 다음 제품 -> 모든 제품으로 이동합니다. 왼쪽 상단에 있는 "내보내기" 버튼을 클릭합니다. 내보낼 제품 데이터를 선택하고 적절한 파일 형식(CSV)을 선택합니다. 마지막으로 "CSV 생성" 버튼을 클릭하여 내보낸 파일을 다운로드합니다.',
        ],
    ],
];
