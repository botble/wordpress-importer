<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'Botble\WordpressImporter\Http\Controllers',
    'middleware' => ['web', 'core'],
], function () {
    Route::group(['prefix' => BaseHelper::getAdminPrefix(), 'middleware' => 'auth'], function () {
        Route::group(['prefix' => '/tools'], function () {
            Route::get('wordpress-importer', [
                'as' => 'wordpress-importer',
                'uses' => 'WordpressImporterController@index',
                'permission' => 'settings.options',
            ]);

            Route::post('wordpress-importer', [
                'as' => 'wordpress-importer.post',
                'uses' => 'WordpressImporterController@import',
                'permission' => 'settings.options',
            ]);
        });
    });
});
