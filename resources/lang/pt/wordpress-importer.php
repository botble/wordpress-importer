<?php

return [
    'name' => 'Importador WordPress',
    'description' => 'Esta ferramenta facilita a importação de dados de blog WordPress como publicações, tags, utilizadores e categorias para o seu sistema. Se precisar importar dados WooCommerce, consulte a secção abaixo para instruções.',
    'copy_images' => 'Copiar imagens',
    'copy_images_description' => 'As imagens destacadas de publicações e páginas serão copiadas para o seu armazenamento. Se selecionar "Não", as referências das imagens permanecerão as mesmas e nenhuma imagem será copiada.',
    'copy_categories' => 'Copiar categorias',
    'copy_categories_description' => 'As categorias das publicações serão copiadas. Se desmarcar, pode selecionar uma categoria padrão para todas as publicações importadas.',
    'options' => 'Opções',
    'select_default_category' => 'Selecionar categoria padrão',
    'loading' => 'A carregar...',
    'upload_xml' => 'Carregue o seu ficheiro de exportação XML do WordPress abaixo e clique em Importar.',
    'timeout_description' => 'Ao copiar publicações e imagens do seu site pode demorar algum tempo se tiver muitos dados. Defina este valor o mais alto possível para evitar o timeout do script.',
    'max_timeout' => 'Timeout máximo em segundos',
    'wp_xml_file' => 'Ficheiro XML WordPress',
    'wp_xml_file_description' => 'Dentro da administração WordPress pode escolher exportar dados visitando Ferramentas->Exportar.',
    'import' => 'Importar',
    'xml_file_required' => 'Por favor especifique um ficheiro XML WordPress que deseja carregar.',
    'invalid_xml_file' => 'Tipo de ficheiro inválido. Certifique-se de que está a carregar um ficheiro de exportação XML WordPress.',
    'import_success' => 'Importados com sucesso :posts publicações, :pages páginas, :categories categorias, :tags tags e :users utilizadores!',
    'load_seo_meta' => 'Carregar meta SEO',
    'load_seo_meta_description' => 'Carregar meta SEO (para publicações e páginas) do plugin WordPress Yoast SEO.',
    'load_more' => 'Carregar mais',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Produtos WooCommerce',
            'description' => 'Importar dados de produtos WooCommerce de ficheiro CSV.',
            'export_instruction' => 'Para obter o ficheiro CSV de produtos WooCommerce, navegue até ao painel de administração WordPress, depois vá a Produtos -> Todos os produtos. Clique no botão "Exportar" no canto superior esquerdo. Selecione os dados do produto que deseja exportar e escolha o formato de ficheiro adequado (CSV). Por fim, clique em "Gerar CSV" para descarregar o ficheiro exportado.',
        ],
    ],
];
