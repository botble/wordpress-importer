<?php

return [
    'name' => 'Importer WordPress',
    'description' => 'To narzędzie ułatwia importowanie danych bloga WordPress, takich jak wpisy, tagi, użytkownicy i kategorie do systemu. Jeśli musisz zaimportować dane WooCommerce, zapoznaj się z sekcją poniżej.',
    'copy_images' => 'Kopiuj obrazy',
    'copy_images_description' => 'Obrazy wyróżniające wpisów i stron zostaną skopiowane do magazynu. Jeśli wybierzesz "Nie", odniesienia do obrazów pozostaną takie same i żadne obrazy nie zostaną skopiowane.',
    'copy_categories' => 'Kopiuj kategorie',
    'copy_categories_description' => 'Kategorie wpisów zostaną skopiowane. Jeśli odznaczysz, możesz wybrać domyślną kategorię dla wszystkich importowanych wpisów.',
    'options' => 'Opcje',
    'select_default_category' => 'Wybierz domyślną kategorię',
    'loading' => 'Ładowanie...',
    'upload_xml' => 'Prześlij plik eksportu XML WordPress poniżej i kliknij Importuj.',
    'timeout_description' => 'Kopiowanie wpisów i obrazów z Twojej strony może zająć trochę czasu, jeśli masz dużo danych. Ustaw tę wartość jak najwyżej, aby zapobiec przekroczeniu limitu czasu skryptu.',
    'max_timeout' => 'Maksymalny limit czasu w sekundach',
    'wp_xml_file' => 'Plik XML WordPress',
    'wp_xml_file_description' => 'W panelu administracyjnym WordPress możesz wyeksportować dane, odwiedzając Narzędzia->Eksport.',
    'import' => 'Importuj',
    'xml_file_required' => 'Proszę określić plik XML WordPress, który chcesz przesłać.',
    'invalid_xml_file' => 'Nieprawidłowy typ pliku. Upewnij się, że przesyłasz plik eksportu XML WordPress.',
    'import_success' => 'Pomyślnie zaimportowano :posts wpisów, :pages stron, :categories kategorii, :tags tagów i :users użytkowników!',
    'load_seo_meta' => 'Załaduj meta SEO',
    'load_seo_meta_description' => 'Załaduj meta SEO (dla wpisów i stron) z wtyczki WordPress Yoast SEO.',
    'load_more' => 'Załaduj więcej',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Produkty WooCommerce',
            'description' => 'Importuj dane produktów WooCommerce z pliku CSV.',
            'export_instruction' => 'Aby uzyskać plik CSV produktów WooCommerce, przejdź do panelu administracyjnego WordPress, następnie do Produkty -> Wszystkie produkty. Kliknij przycisk "Eksportuj" w lewym górnym rogu. Wybierz dane produktów do eksportu i wybierz odpowiedni format pliku (CSV). Na koniec kliknij "Generuj CSV", aby pobrać wyeksportowany plik.',
        ],
    ],
];
