<?php

namespace Botble\WordpressImporter\Providers;

use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\ServiceProvider;

class WordpressImporterServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this->setNamespace('plugins/wordpress-importer')
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets()
            ->loadRoutes();

        $this->app['events']->listen(RouteMatched::class, function () {
            if (! dashboard_menu()->hasItem('cms-core-tools')) {
                dashboard_menu()->registerItem([
                    'id' => 'cms-core-tools',
                    'priority' => 96,
                    'parent_id' => null,
                    'name' => 'core/base::base.tools',
                    'icon' => 'fas fa-tools',
                    'url' => '',
                    'permissions' => [],
                ]);
            }

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
