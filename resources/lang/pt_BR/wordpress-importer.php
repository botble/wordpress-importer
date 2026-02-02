<?php

return [
    'name' => 'Importador WordPress',
    'description' => 'Esta ferramenta facilita a importação de dados de blog WordPress como posts, tags, usuários e categorias para o seu sistema. Se precisar importar dados WooCommerce, consulte a seção abaixo para instruções.',
    'copy_images' => 'Copiar imagens',
    'copy_images_description' => 'As imagens destacadas de posts e páginas serão copiadas para o seu armazenamento. Se selecionar "Não", as referências das imagens permanecerão as mesmas e nenhuma imagem será copiada.',
    'copy_categories' => 'Copiar categorias',
    'copy_categories_description' => 'As categorias dos posts serão copiadas. Se desmarcar, você pode selecionar uma categoria padrão para todos os posts importados.',
    'options' => 'Opções',
    'select_default_category' => 'Selecionar categoria padrão',
    'loading' => 'Carregando...',
    'upload_xml' => 'Faça upload do seu arquivo de exportação XML do WordPress abaixo e clique em Importar.',
    'timeout_description' => 'Ao copiar posts e imagens do seu site pode demorar um tempo se você tiver muitos dados. Defina este valor o mais alto possível para evitar o timeout do script.',
    'max_timeout' => 'Timeout máximo em segundos',
    'wp_xml_file' => 'Arquivo XML WordPress',
    'wp_xml_file_description' => 'Dentro da administração WordPress você pode escolher exportar dados visitando Ferramentas->Exportar.',
    'import' => 'Importar',
    'xml_file_required' => 'Por favor especifique um arquivo XML WordPress que deseja fazer upload.',
    'invalid_xml_file' => 'Tipo de arquivo inválido. Certifique-se de que está fazendo upload de um arquivo de exportação XML WordPress.',
    'import_success' => 'Importados com sucesso :posts posts, :pages páginas, :categories categorias, :tags tags e :users usuários!',
    'load_seo_meta' => 'Carregar meta SEO',
    'load_seo_meta_description' => 'Carregar meta SEO (para posts e páginas) do plugin WordPress Yoast SEO.',
    'load_more' => 'Carregar mais',
    'data_synchronize' => [
        'import_products' => [
            'name' => 'Produtos WooCommerce',
            'description' => 'Importar dados de produtos WooCommerce de arquivo CSV.',
            'export_instruction' => 'Para obter o arquivo CSV de produtos WooCommerce, navegue até o painel de administração WordPress, depois vá para Produtos -> Todos os produtos. Clique no botão "Exportar" no canto superior esquerdo. Selecione os dados do produto que deseja exportar e escolha o formato de arquivo adequado (CSV). Por fim, clique em "Gerar CSV" para baixar o arquivo exportado.',
        ],
    ],
];
