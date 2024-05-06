<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Botble\DataSynchronize\Http\Controllers\ImportController;
use Botble\DataSynchronize\Importer\Importer;
use Botble\WordpressImporter\Importers\ProductImporter;

class ImportProductController extends ImportController
{
    protected function getImporter(): Importer
    {
        return ProductImporter::make();
    }
}
