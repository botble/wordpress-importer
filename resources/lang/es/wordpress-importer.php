<?php

return [
    'name' => 'Importador de WordPress',
    'description' => 'Esta herramienta facilita la importación de datos de blog de WordPress como publicaciones, etiquetas, usuarios y categorías en su sistema. Si necesita importar datos de WooCommerce, consulte la sección a continuación para obtener instrucciones.',
    'copy_images' => 'Copiar imágenes',
    'copy_images_description' => 'Las imágenes destacadas de publicaciones y páginas se copiarán a su almacenamiento. Si selecciona "No", las referencias de imágenes permanecerán iguales y no se copiarán imágenes.',
    'copy_categories' => 'Copiar categorías',
    'copy_categories_description' => 'Se copiarán las categorías de las publicaciones. Si desmarca, puede seleccionar una categoría predeterminada para todas las publicaciones importadas.',
    'options' => 'Opciones',
    'select_default_category' => 'Seleccionar categoría predeterminada',
    'loading' => 'Cargando...',
    'upload_xml' => 'Suba su archivo de exportación XML de WordPress a continuación y haga clic en Importar.',
    'timeout_description' => 'Al copiar publicaciones e imágenes de su sitio, puede tomar un tiempo si tiene muchos datos. Establezca este valor tan alto como desee para evitar que el script se agote.',
    'max_timeout' => 'Tiempo de espera máximo en segundos',
    'wp_xml_file' => 'Archivo XML de WordPress',
    'wp_xml_file_description' => 'Dentro de su administración de WordPress puede elegir exportar datos visitando Herramientas->Exportar.',
    'import' => 'Importar',
    'xml_file_required' => 'Por favor especifique un archivo XML de WordPress que desee subir.',
    'invalid_xml_file' => 'Tipo de archivo no válido. Asegúrese de que está subiendo un archivo de exportación XML de WordPress.',
    'import_success' => '¡Se importaron :posts publicaciones, :pages páginas, :categories categorías, :tags etiquetas y :users usuarios con éxito!',
    'load_seo_meta' => 'Cargar meta SEO',
    'load_seo_meta_description' => 'Cargar meta SEO (para publicaciones y páginas) desde el plugin Yoast SEO de WordPress.',
    'load_more' => 'Cargar más',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Productos WooCommerce',
            'description' => 'Importar datos de productos WooCommerce desde archivo CSV.',
            'export_instruction' => 'Para obtener su archivo CSV de productos WooCommerce, navegue al panel de administración de WordPress, luego vaya a Productos -> Todos los productos. Haga clic en el botón "Exportar" ubicado en la esquina superior izquierda. Seleccione los datos del producto que desea exportar y elija el formato de archivo apropiado (CSV). Finalmente, haga clic en el botón "Generar CSV" para descargar el archivo exportado.',
        ],
    ],
];
