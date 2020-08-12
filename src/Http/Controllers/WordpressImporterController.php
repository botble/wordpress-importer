<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Assets;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\WordpressImporter\Http\Requests\WordpressImporterRequest;
use Botble\WordpressImporter\WordpressImporter;

class WordpressImporterController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        Assets::addScriptsDirectly('vendor/core/plugins/wordpress-importer/js/wordpress-importer.js');

        page_title()->setTitle(__('Wordpress Importer'));

        return view('plugins/wordpress-importer::import');
    }

    /**
     * @param WordpressImporterRequest $request
     * @param BaseHttpResponse $response
     * @param WordpressImporter $wordpressImporter
     * @return BaseHttpResponse
     */
    public function import(
        WordpressImporterRequest $request,
        BaseHttpResponse $response,
        WordpressImporter $wordpressImporter
    ) {
        if (!$request->hasFile('wpexport')) {
            return $response
                ->setError()
                ->setMessage(__('Please specify a Wordpress XML file that you would like to upload.'));
        }

        $mimeType = $request->file('wpexport')->getMimeType();

        if (!in_array($mimeType, ['text/xml', 'application/xml'])) {
            return $response
                ->setError()
                ->setMessage(__('Invalid file type. Please make sure you are uploading a Wordpress XML export file.'));
        }

        $xmlFile = file_get_contents($request->file('wpexport'));
        $isCopyImages = (bool)$request->input('copyimages');
        $timeout = $request->input('timeout', 900);

        $result = $wordpressImporter->import($xmlFile, $isCopyImages, $timeout);

        return $response
            ->setMessage(__('Imported :posts posts, :pages pages, :categories categories, :tags tags, and :users users successfully !',
                $result));
    }
}
