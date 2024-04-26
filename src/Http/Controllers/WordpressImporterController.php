<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Botble\Base\Facades\Assets;
use Botble\Base\Http\Controllers\BaseController;
use Botble\WordpressImporter\Http\Requests\WordpressImporterRequest;
use Botble\WordpressImporter\WordpressImporter;

class WordpressImporterController extends BaseController
{
    public function index()
    {
        Assets::addScriptsDirectly('vendor/core/plugins/wordpress-importer/js/wordpress-importer.js')
            ->addStylesDirectly('vendor/core/plugins/wordpress-importer/css/wordpress-importer.css');

        $this->pageTitle(trans('plugins/wordpress-importer::wordpress-importer.name'));

        return view('plugins/wordpress-importer::import');
    }

    public function import(WordpressImporterRequest $request, WordpressImporter $wordpressImporter)
    {
        $validate = $wordpressImporter->verifyRequest($request);

        if ($validate['error']) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($validate['message']);
        }

        $result = $wordpressImporter->import();

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.import_success', $result));
    }
}
