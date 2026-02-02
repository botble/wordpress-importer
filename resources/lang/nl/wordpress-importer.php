<?php

return [
    'name' => 'WordPress Importeur',
    'description' => 'Deze tool vergemakkelijkt het importeren van WordPress bloggegevens zoals berichten, tags, gebruikers en categorieën in uw systeem. Als u WooCommerce-gegevens moet importeren, raadpleeg dan het onderstaande gedeelte voor instructies.',
    'copy_images' => 'Afbeeldingen kopiëren',
    'copy_images_description' => 'Uitgelichte afbeeldingen voor berichten en pagina\'s worden naar uw opslag gekopieerd. Als u "Nee" selecteert, blijven de afbeeldingsverwijzingen hetzelfde en worden er geen afbeeldingen gekopieerd.',
    'copy_categories' => 'Categorieën kopiëren',
    'copy_categories_description' => 'Categorieën voor berichten worden gekopieerd. Als u dit uitschakelt, kunt u een standaardcategorie selecteren voor alle geïmporteerde berichten.',
    'options' => 'Opties',
    'select_default_category' => 'Standaardcategorie selecteren',
    'loading' => 'Laden...',
    'upload_xml' => 'Upload hieronder uw WordPress XML-exportbestand en klik op Importeren.',
    'timeout_description' => 'Het kopiëren van berichten en afbeeldingen van uw site kan even duren als u veel gegevens hebt. Stel dit zo hoog mogelijk in om een time-out van het script te voorkomen.',
    'max_timeout' => 'Maximale time-out in seconden',
    'wp_xml_file' => 'WordPress XML-bestand',
    'wp_xml_file_description' => 'In uw WordPress-beheer kunt u gegevens exporteren via Extra->Exporteren.',
    'import' => 'Importeren',
    'xml_file_required' => 'Geef een WordPress XML-bestand op dat u wilt uploaden.',
    'invalid_xml_file' => 'Ongeldig bestandstype. Zorg ervoor dat u een WordPress XML-exportbestand uploadt.',
    'import_success' => ':posts berichten, :pages pagina\'s, :categories categorieën, :tags tags en :users gebruikers succesvol geïmporteerd!',
    'load_seo_meta' => 'SEO-metadata laden',
    'load_seo_meta_description' => 'SEO-metadata (voor berichten en pagina\'s) laden vanuit de WordPress Yoast SEO-plugin.',
    'load_more' => 'Meer laden',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce-producten',
            'description' => 'WooCommerce-productgegevens importeren uit CSV-bestand.',
            'export_instruction' => 'Om uw WooCommerce-producten CSV-bestand te verkrijgen, navigeert u naar het WordPress-beheerdersdashboard en gaat u naar Producten -> Alle producten. Klik op de knop "Exporteren" linksboven. Selecteer de productgegevens die u wilt exporteren en kies het juiste bestandsformaat (CSV). Klik ten slotte op "CSV genereren" om het geëxporteerde bestand te downloaden.',
        ],
    ],
];
