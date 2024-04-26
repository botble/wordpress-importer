<?php

use Botble\Base\Facades\AdminHelper;
use Botble\WordpressImporter\Http\Controllers\WordpressImporterController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function () {
    Route::group([
        'controller' => WordpressImporterController::class,
        'prefix' => '/tools/wordpress-importer',
    ], function () {
        Route::get('/', [
            'as' => 'wordpress-importer',
            'uses' => 'index',
            'permission' => 'settings.options',
        ]);

        Route::post('/', [
            'as' => 'wordpress-importer.post',
            'uses' => 'import',
            'permission' => 'settings.options',
        ]);
    });
});
