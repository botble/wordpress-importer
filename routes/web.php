<?php

Route::group(['namespace' => 'Botble\WordpressImporter\Http\Controllers', 'middleware' => 'web'], function () {
    Route::group(['prefix' => config('core.base.general.admin_dir'), 'middleware' => 'auth'], function () {
        Route::get('wordpress-importer', [
            'as'   => 'wordpress-importer',
            'uses' => 'WordpressImporterController@index',
        ]);

        Route::post('wordpress-importer', [
            'as'   => 'wordpress-importer',
            'uses' => 'WordpressImporterController@import',
        ]);
    });
});
