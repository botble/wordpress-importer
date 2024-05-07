<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Botble\Base\Facades\Assets;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Blog\Models\Category;
use Botble\WordpressImporter\Forms\WordpressImporterForm;
use Botble\WordpressImporter\Http\Requests\WordpressImporterRequest;
use Botble\WordpressImporter\Importers\ProductImporter;
use Botble\WordpressImporter\WordpressImporter;

class WordpressImporterController extends BaseController
{
    public function index()
    {
        Assets::addScriptsDirectly('vendor/core/plugins/wordpress-importer/js/wordpress-importer.js');

        $this->pageTitle(trans('plugins/wordpress-importer::wordpress-importer.name'));

        $form = WordpressImporterForm::create();
        $productImporter = ProductImporter::make();

        return view('plugins/wordpress-importer::import', compact('form', 'productImporter'));
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

    public function ajaxCategories()
    {
        return $this
            ->httpResponse()
            ->setData(Category::query()->select('name', 'id')->paginate());
    }
}
