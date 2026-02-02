<?php

return [
    'name' => 'Importateur WordPress',
    'description' => 'Cet outil facilite l\'importation des données de blog WordPress telles que les articles, les étiquettes, les utilisateurs et les catégories dans votre système. Si vous devez importer des données WooCommerce, veuillez consulter la section ci-dessous pour les instructions.',
    'copy_images' => 'Copier les images',
    'copy_images_description' => 'Les images mises en avant des articles et des pages seront copiées dans votre stockage. Si vous sélectionnez "Non", les références des images resteront les mêmes et aucune image ne sera copiée.',
    'copy_categories' => 'Copier les catégories',
    'copy_categories_description' => 'Les catégories des articles seront copiées. Si vous décochez, vous pouvez sélectionner une catégorie par défaut pour tous les articles importés.',
    'options' => 'Options',
    'select_default_category' => 'Sélectionner la catégorie par défaut',
    'loading' => 'Chargement...',
    'upload_xml' => 'Téléchargez votre fichier d\'exportation XML WordPress ci-dessous et cliquez sur Importer.',
    'timeout_description' => 'Lors de la copie des articles et des images de votre site, cela peut prendre un certain temps si vous avez beaucoup de données. Réglez cette valeur aussi haut que vous le souhaitez pour éviter l\'expiration du délai du script.',
    'max_timeout' => 'Délai maximum en secondes',
    'wp_xml_file' => 'Fichier XML WordPress',
    'wp_xml_file_description' => 'Dans votre administration WordPress, vous pouvez choisir d\'exporter les données en visitant Outils->Exporter.',
    'import' => 'Importer',
    'xml_file_required' => 'Veuillez spécifier un fichier XML WordPress que vous souhaitez télécharger.',
    'invalid_xml_file' => 'Type de fichier invalide. Veuillez vous assurer que vous téléchargez un fichier d\'exportation XML WordPress.',
    'import_success' => ':posts articles, :pages pages, :categories catégories, :tags étiquettes et :users utilisateurs importés avec succès !',
    'load_seo_meta' => 'Charger les métadonnées SEO',
    'load_seo_meta_description' => 'Charger les métadonnées SEO (pour les articles et les pages) depuis le plugin WordPress Yoast SEO.',
    'load_more' => 'Charger plus',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Produits WooCommerce',
            'description' => 'Importer les données des produits WooCommerce depuis un fichier CSV.',
            'export_instruction' => 'Pour obtenir votre fichier CSV des produits WooCommerce, accédez au tableau de bord d\'administration WordPress, puis allez dans Produits -> Tous les produits. Cliquez sur le bouton "Exporter" situé en haut à gauche. Sélectionnez les données de produit que vous souhaitez exporter et choisissez le format de fichier approprié (CSV). Enfin, cliquez sur le bouton "Générer CSV" pour télécharger le fichier exporté.',
        ],
    ],
];
