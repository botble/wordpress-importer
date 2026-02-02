<?php

return [
    'name' => 'Importatore WordPress',
    'description' => 'Questo strumento facilita l\'importazione dei dati del blog WordPress come articoli, tag, utenti e categorie nel tuo sistema. Se hai bisogno di importare dati WooCommerce, consulta la sezione sottostante per le istruzioni.',
    'copy_images' => 'Copia immagini',
    'copy_images_description' => 'Le immagini in evidenza per articoli e pagine verranno copiate nel tuo archivio. Se selezioni "No", i riferimenti delle immagini rimarranno gli stessi e nessuna immagine verrà copiata.',
    'copy_categories' => 'Copia categorie',
    'copy_categories_description' => 'Le categorie degli articoli verranno copiate. Se deselezioni, puoi selezionare una categoria predefinita per tutti gli articoli importati.',
    'options' => 'Opzioni',
    'select_default_category' => 'Seleziona categoria predefinita',
    'loading' => 'Caricamento...',
    'upload_xml' => 'Carica il tuo file di esportazione XML di WordPress qui sotto e clicca su Importa.',
    'timeout_description' => 'Durante la copia di articoli e immagini dal tuo sito potrebbe richiedere del tempo se hai molti dati. Imposta questo valore il più alto possibile per evitare il timeout dello script.',
    'max_timeout' => 'Timeout massimo in secondi',
    'wp_xml_file' => 'File XML WordPress',
    'wp_xml_file_description' => 'All\'interno dell\'amministrazione WordPress puoi scegliere di esportare i dati visitando Strumenti->Esporta.',
    'import' => 'Importa',
    'xml_file_required' => 'Specifica un file XML di WordPress che desideri caricare.',
    'invalid_xml_file' => 'Tipo di file non valido. Assicurati di caricare un file di esportazione XML di WordPress.',
    'import_success' => 'Importati con successo :posts articoli, :pages pagine, :categories categorie, :tags tag e :users utenti!',
    'load_seo_meta' => 'Carica meta SEO',
    'load_seo_meta_description' => 'Carica meta SEO (per articoli e pagine) dal plugin WordPress Yoast SEO.',
    'load_more' => 'Carica altro',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Prodotti WooCommerce',
            'description' => 'Importa dati prodotti WooCommerce da file CSV.',
            'export_instruction' => 'Per ottenere il file CSV dei prodotti WooCommerce, vai alla dashboard di amministrazione WordPress, poi vai su Prodotti -> Tutti i prodotti. Clicca sul pulsante "Esporta" in alto a sinistra. Seleziona i dati del prodotto che desideri esportare e scegli il formato file appropriato (CSV). Infine, clicca su "Genera CSV" per scaricare il file esportato.',
        ],
    ],
];
