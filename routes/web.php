<?php

use Botble\Base\Facades\AdminHelper;
use Botble\Base\Http\Middleware\RequiresJsonRequestMiddleware;
use Botble\WordpressImporter\Http\Controllers\WordpressImporterController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function () {
    Route::group([
        'controller' => WordpressImporterController::class,
        'prefix' => '/tools/wordpress-importer',
        'permission' => 'settings.options',
    ], function () {
        Route::get('/', [WordpressImporterController::class, 'index'])->name('wordpress-importer');
        Route::post('/', [WordpressImporterController::class, 'import'])->name('wordpress-importer.post');

        if (is_plugin_active('blog')) {
            Route::post('/ajax/categories', [WordpressImporterController::class, 'ajaxCategories'])
                ->middleware(RequiresJsonRequestMiddleware::class)
                ->name('wordpress-importer.ajax.categories');
        }
    });
});
