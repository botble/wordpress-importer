<?php

use Botble\Base\Facades\AdminHelper;
use Botble\Base\Http\Middleware\RequiresJsonRequestMiddleware;
<<<<<<< Updated upstream
=======
use Botble\WordpressImporter\Http\Controllers\ImportProductController;
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======
    });

    Route::prefix('tools/data-synchronize')->name('tools.data-synchronize.')->group(function () {
        Route::prefix('import')->name('import.')->group(function () {
            Route::group(['prefix' => 'woocommerce-products', 'as' => 'woocommerce-products.', 'permission' => 'settings.options'], function () {
                Route::get('/', [ImportProductController::class, 'index'])->name('index');
                Route::post('/', [ImportProductController::class, 'import'])->name('store');
                Route::post('validate', [ImportProductController::class, 'validateData'])->name('validate');
                Route::post('download-example', [ImportProductController::class, 'downloadExample'])->name('download-example');
            });
        });
>>>>>>> Stashed changes
    });
});
