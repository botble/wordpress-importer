<?php

namespace Botble\WordpressImporter\Providers;

use Botble\Base\Traits\LoadAndPublishDataTrait;
use Event;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\ServiceProvider;

class WordpressImporterServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!defined('POST_MODULE_SCREEN_NAME') && !defined('PAGE_MODULE_SCREEN_NAME')) return;

        $this->setNamespace('plugins/wordpress-importer')
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadRoutes(['web']);

        Event::listen(RouteMatched::class, function () {
            dashboard_menu()
                ->registerItem([
                    'id'          => 'cms-plugin-wordpress-importer',
                    'priority'    => 99,
                    'parent_id'   => 'cms-core-settings',
                    'name'        => 'Wordpress Import',
                    'icon'        => null,
                    'url'         => route('wordpress-importer'),
                    'permissions' => ['settings.options'],
                ]);
        });
    }
}
