<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Assets;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\WordpressImporter\WordpressImporter;

class WordpressImporterController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        Assets::addScriptsDirectly('vendor/core/plugins/wordpress-importer/js/wordpress-importer.js')
            ->addStylesDirectly('vendor/core/plugins/wordpress-importer/css/wordpress-importer.css');

        page_title()->setTitle(__('Wordpress Importer'));

        return view('plugins/wordpress-importer::import');
    }

    /**
     * @param BaseHttpResponse $response
     * @param WordpressImporter $wordpressImporter
     * @return BaseHttpResponse
     */
    public function import(
        BaseHttpResponse $response,
        WordpressImporter $wordpressImporter
    ) {
        if ($wordpressImporter->hasError()) {
            return $response
                ->setError()
                ->setMessage($wordpressImporter->getError());
        }

        $result = $wordpressImporter->import();

        return $response
            ->setMessage(__('Imported :posts posts, :pages pages, :categories categories, :tags tags, and :users users successfully !',
                $result));
    }
}
