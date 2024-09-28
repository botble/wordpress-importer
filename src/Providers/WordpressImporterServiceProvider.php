<?php

namespace Botble\WordpressImporter\Providers;

use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\DataSynchronize\Importer\Importer;
use Botble\WordpressImporter\Importers\ProductImporter;
use Illuminate\Support\ServiceProvider;

class WordpressImporterServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/wordpress-importer')
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets()
            ->loadRoutes();

        DashboardMenu::beforeRetrieving(function () {
            DashboardMenu::registerItem([
                'id' => 'cms-plugin-wordpress-importer',
                'priority' => 99,
                'parent_id' => 'cms-core-tools',
                'name' => 'plugins/wordpress-importer::wordpress-importer.name',
                'icon' => 'fab fa-wordpress',
                'url' => route('wordpress-importer'),
                'permissions' => ['settings.options'],
            ]);
        });

        add_filter('data_synchronize_import_form_before', function (?string $html, Importer $importer): ?string {
            if (! $importer instanceof ProductImporter) {
                return $html;
            }

            return $html . view('plugins/wordpress-importer::partials.woocommerce-products-export-instruction');
        }, 999, 2);
    }
}
