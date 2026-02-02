<?php

return [
    'name' => 'Pengimpor WordPress',
    'description' => 'Alat ini memudahkan impor data blog WordPress seperti postingan, tag, pengguna, dan kategori ke sistem Anda. Jika Anda perlu mengimpor data WooCommerce, silakan lihat bagian di bawah untuk instruksi.',
    'copy_images' => 'Salin gambar',
    'copy_images_description' => 'Gambar unggulan untuk postingan dan halaman akan disalin ke penyimpanan Anda. Jika Anda memilih "Tidak", referensi gambar akan tetap sama dan tidak ada gambar yang akan disalin.',
    'copy_categories' => 'Salin kategori',
    'copy_categories_description' => 'Kategori untuk postingan akan disalin. Jika Anda menghapus centang, Anda dapat memilih kategori default untuk semua postingan yang diimpor.',
    'options' => 'Opsi',
    'select_default_category' => 'Pilih kategori default',
    'loading' => 'Memuat...',
    'upload_xml' => 'Unggah file ekspor XML WordPress Anda di bawah dan klik Impor.',
    'timeout_description' => 'Saat menyalin postingan dan gambar dari situs Anda mungkin memerlukan waktu jika Anda memiliki banyak data. Atur nilai ini setinggi mungkin untuk mencegah skrip kehabisan waktu.',
    'max_timeout' => 'Batas waktu maksimum dalam detik',
    'wp_xml_file' => 'File XML WordPress',
    'wp_xml_file_description' => 'Di dalam Admin WordPress Anda dapat memilih untuk mengekspor data dengan mengunjungi Alat->Ekspor.',
    'import' => 'Impor',
    'xml_file_required' => 'Silakan tentukan file XML WordPress yang ingin Anda unggah.',
    'invalid_xml_file' => 'Jenis file tidak valid. Pastikan Anda mengunggah file ekspor XML WordPress.',
    'import_success' => 'Berhasil mengimpor :posts postingan, :pages halaman, :categories kategori, :tags tag, dan :users pengguna!',
    'load_seo_meta' => 'Muat meta SEO',
    'load_seo_meta_description' => 'Muat Meta SEO (untuk Postingan dan Halaman) dari plugin WordPress Yoast SEO.',
    'load_more' => 'Muat lebih banyak',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Produk WooCommerce',
            'description' => 'Impor data produk WooCommerce dari file CSV.',
            'export_instruction' => 'Untuk mendapatkan file CSV produk WooCommerce, navigasikan ke dasbor admin WordPress, lalu buka Produk -> Semua Produk. Klik tombol "Ekspor" di pojok kiri atas. Pilih data produk yang ingin diekspor dan pilih format file yang sesuai (CSV). Terakhir, klik tombol "Hasilkan CSV" untuk mengunduh file yang diekspor.',
        ],
    ],
];
