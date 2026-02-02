<?php

return [
    'name' => 'WordPress İçe Aktarıcı',
    'description' => 'Bu araç, yazılar, etiketler, kullanıcılar ve kategoriler gibi WordPress blog verilerinin sisteminize aktarılmasını kolaylaştırır. WooCommerce verilerini içe aktarmanız gerekiyorsa, talimatlar için aşağıdaki bölüme bakın.',
    'copy_images' => 'Görselleri kopyala',
    'copy_images_description' => 'Yazılar ve sayfalar için öne çıkan görseller depolama alanınıza kopyalanacaktır. "Hayır" seçerseniz görsel referansları aynı kalacak ve hiçbir görsel kopyalanmayacaktır.',
    'copy_categories' => 'Kategorileri kopyala',
    'copy_categories_description' => 'Yazı kategorileri kopyalanacaktır. İşareti kaldırırsanız, tüm içe aktarılan yazılar için varsayılan kategori seçebilirsiniz.',
    'options' => 'Seçenekler',
    'select_default_category' => 'Varsayılan kategoriyi seç',
    'loading' => 'Yükleniyor...',
    'upload_xml' => 'WordPress XML dışa aktarma dosyanızı aşağıya yükleyin ve İçe Aktar\'a tıklayın.',
    'timeout_description' => 'Sitenizden yazıları ve görselleri kopyalarken çok fazla veriniz varsa biraz zaman alabilir. Komut dosyasının zaman aşımına uğramasını önlemek için bu değeri olabildiğince yüksek ayarlayın.',
    'max_timeout' => 'Maksimum zaman aşımı (saniye)',
    'wp_xml_file' => 'WordPress XML dosyası',
    'wp_xml_file_description' => 'WordPress Yönetici panelinde Araçlar->Dışa Aktar bölümünü ziyaret ederek verileri dışa aktarabilirsiniz.',
    'import' => 'İçe Aktar',
    'xml_file_required' => 'Lütfen yüklemek istediğiniz bir WordPress XML dosyası belirtin.',
    'invalid_xml_file' => 'Geçersiz dosya türü. WordPress XML dışa aktarma dosyası yüklediğinizden emin olun.',
    'import_success' => ':posts yazı, :pages sayfa, :categories kategori, :tags etiket ve :users kullanıcı başarıyla içe aktarıldı!',
    'load_seo_meta' => 'SEO Meta yükle',
    'load_seo_meta_description' => 'WordPress Yoast SEO eklentisinden SEO Meta (yazılar ve sayfalar için) yükleyin.',
    'load_more' => 'Daha fazla yükle',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce Ürünleri',
            'description' => 'CSV dosyasından WooCommerce ürün verilerini içe aktarın.',
            'export_instruction' => 'WooCommerce ürün CSV dosyanızı elde etmek için WordPress yönetici paneline gidin, ardından Ürünler -> Tüm Ürünler\'e gidin. Sol üstteki "Dışa Aktar" düğmesine tıklayın. Dışa aktarmak istediğiniz ürün verilerini seçin ve uygun dosya biçimini (CSV) seçin. Son olarak, dışa aktarılan dosyayı indirmek için "CSV Oluştur" düğmesine tıklayın.',
        ],
    ],
];
