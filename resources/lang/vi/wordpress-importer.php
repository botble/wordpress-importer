<?php

return [
    'name' => 'Nhập WordPress',
    'description' => 'Công cụ này hỗ trợ nhập dữ liệu blog WordPress như bài viết, thẻ, người dùng và danh mục vào hệ thống của bạn. Nếu bạn cần nhập dữ liệu WooCommerce, vui lòng tham khảo phần bên dưới để biết hướng dẫn.',
    'copy_images' => 'Sao chép hình ảnh',
    'copy_images_description' => 'Hình ảnh đại diện cho bài viết và trang sẽ được sao chép vào bộ nhớ của bạn. Nếu bạn chọn "Không" thì các tham chiếu hình ảnh sẽ giữ nguyên và không có hình ảnh nào được sao chép.',
    'copy_categories' => 'Sao chép danh mục',
    'copy_categories_description' => 'Danh mục cho bài viết sẽ được sao chép. Nếu bạn bỏ chọn, bạn có thể chọn danh mục mặc định cho tất cả bài viết được nhập.',
    'options' => 'Tùy chọn',
    'select_default_category' => 'Chọn danh mục mặc định',
    'loading' => 'Đang tải...',
    'upload_xml' => 'Tải lên tệp xuất XML WordPress của bạn bên dưới và nhấn Nhập.',
    'timeout_description' => 'Khi sao chép bài viết và hình ảnh từ trang web của bạn có thể mất một lúc nếu bạn có nhiều dữ liệu, hãy đặt giá trị này cao nhất có thể để tránh hết thời gian chờ của script.',
    'max_timeout' => 'Thời gian chờ tối đa (giây)',
    'wp_xml_file' => 'Tệp XML WordPress',
    'wp_xml_file_description' => 'Trong trang quản trị WordPress, bạn có thể chọn xuất dữ liệu bằng cách vào Công cụ->Xuất.',
    'import' => 'Nhập',
    'xml_file_required' => 'Vui lòng chỉ định tệp XML WordPress mà bạn muốn tải lên.',
    'invalid_xml_file' => 'Loại tệp không hợp lệ. Vui lòng đảm bảo bạn đang tải lên tệp xuất XML WordPress.',
    'import_success' => 'Đã nhập thành công :posts bài viết, :pages trang, :categories danh mục, :tags thẻ và :users người dùng!',
    'load_seo_meta' => 'Tải SEO Meta',
    'load_seo_meta_description' => 'Tải SEO Meta (cho Bài viết và Trang) từ plugin Yoast SEO của WordPress.',
    'load_more' => 'Tải thêm',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Sản phẩm WooCommerce',
            'description' => 'Nhập dữ liệu sản phẩm WooCommerce từ tệp CSV.',
            'export_instruction' => 'Để lấy tệp CSV sản phẩm WooCommerce, hãy vào bảng điều khiển quản trị WordPress, sau đó vào Sản phẩm -> Tất cả sản phẩm. Nhấn nút "Xuất" ở góc trên bên trái. Chọn dữ liệu sản phẩm bạn muốn xuất và chọn định dạng tệp phù hợp (CSV). Cuối cùng, nhấn nút "Tạo CSV" để tải xuống tệp đã xuất.',
        ],
    ],
];
