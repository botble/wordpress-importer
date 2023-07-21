<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Assets;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\WordpressImporter\Http\Requests\WordpressImporterRequest;
use Botble\WordpressImporter\WordpressImporter;

class WordpressImporterController extends BaseController
{
    public function index()
    {
        Assets::addScriptsDirectly('vendor/core/plugins/wordpress-importer/js/wordpress-importer.js')
            ->addStylesDirectly('vendor/core/plugins/wordpress-importer/css/wordpress-importer.css');

        page_title()->setTitle(trans('plugins/wordpress-importer::wordpress-importer.name'));

        return view('plugins/wordpress-importer::import');
    }

    public function import(
        WordpressImporterRequest $request,
        BaseHttpResponse $response,
        WordpressImporter $wordpressImporter
    ) {
        $validate = $wordpressImporter->verifyRequest($request);

        if ($validate['error']) {
            return $response
                ->setError()
                ->setMessage($validate['message']);
        }

        $result = $wordpressImporter->import();

        return $response
            ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.import_success', $result));
    }
}
