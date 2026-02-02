<?php

return [
    'name' => 'WordPress-Importer',
    'description' => 'Dieses Tool erleichtert den Import von WordPress-Blog-Daten wie Beiträgen, Schlagwörtern, Benutzern und Kategorien in Ihr System. Wenn Sie WooCommerce-Daten importieren müssen, lesen Sie bitte den Abschnitt unten für Anweisungen.',
    'copy_images' => 'Bilder kopieren',
    'copy_images_description' => 'Beitragsbilder für Beiträge und Seiten werden in Ihren Speicher kopiert. Wenn Sie "Nein" wählen, bleiben die Bildreferenzen gleich und es werden keine Bilder kopiert.',
    'copy_categories' => 'Kategorien kopieren',
    'copy_categories_description' => 'Kategorien für Beiträge werden kopiert. Wenn Sie die Option deaktivieren, können Sie eine Standardkategorie für alle importierten Beiträge auswählen.',
    'options' => 'Optionen',
    'select_default_category' => 'Standardkategorie auswählen',
    'loading' => 'Laden...',
    'upload_xml' => 'Laden Sie Ihre WordPress-XML-Exportdatei unten hoch und klicken Sie auf Importieren.',
    'timeout_description' => 'Beim Kopieren von Beiträgen und Bildern von Ihrer Website kann es eine Weile dauern, wenn Sie viele Daten haben. Setzen Sie diesen Wert so hoch wie gewünscht, um ein Timeout des Skripts zu verhindern.',
    'max_timeout' => 'Maximales Timeout in Sekunden',
    'wp_xml_file' => 'WordPress-XML-Datei',
    'wp_xml_file_description' => 'In Ihrer WordPress-Administration können Sie Daten exportieren, indem Sie Werkzeuge->Exportieren besuchen.',
    'import' => 'Importieren',
    'xml_file_required' => 'Bitte geben Sie eine WordPress-XML-Datei an, die Sie hochladen möchten.',
    'invalid_xml_file' => 'Ungültiger Dateityp. Bitte stellen Sie sicher, dass Sie eine WordPress-XML-Exportdatei hochladen.',
    'import_success' => ':posts Beiträge, :pages Seiten, :categories Kategorien, :tags Schlagwörter und :users Benutzer erfolgreich importiert!',
    'load_seo_meta' => 'SEO-Metadaten laden',
    'load_seo_meta_description' => 'SEO-Metadaten (für Beiträge und Seiten) aus dem WordPress Yoast SEO Plugin laden.',
    'load_more' => 'Mehr laden',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'WooCommerce-Produkte',
            'description' => 'WooCommerce-Produktdaten aus einer CSV-Datei importieren.',
            'export_instruction' => 'Um Ihre WooCommerce-Produkte-CSV-Datei zu erhalten, navigieren Sie zum WordPress-Admin-Dashboard, dann zu Produkte -> Alle Produkte. Klicken Sie auf die Schaltfläche "Exportieren" oben links. Wählen Sie die Produktdaten aus, die Sie exportieren möchten, und wählen Sie das entsprechende Dateiformat (CSV). Klicken Sie abschließend auf "CSV generieren", um die exportierte Datei herunterzuladen.',
        ],
    ],
];
