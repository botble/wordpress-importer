<?php

namespace Botble\WordpressImporter\Providers;

use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Traits\LoadAndPublishDataTrait;
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
            dashboard_menu()
                ->registerItem([
                    'id' => 'cms-plugin-wordpress-importer',
                    'priority' => 99,
                    'parent_id' => 'cms-core-tools',
                    'name' => 'plugins/wordpress-importer::wordpress-importer.name',
                    'icon' => 'fab fa-wordpress',
                    'url' => route('wordpress-importer'),
                    'permissions' => ['settings.options'],
                ]);
        });
    }
}
