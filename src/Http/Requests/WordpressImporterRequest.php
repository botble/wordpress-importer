<?php

namespace Botble\WordpressImporter\Http\Requests;

use Botble\Base\Rules\OnOffRule;
use Botble\Support\Http\Requests\Request;

class WordpressImporterRequest extends Request
{
    public function rules(): array
    {
        return [
            'wpexport' => ['required', 'mimetypes:text/xml,application/xml'],
            'copy_categories' => [new OnOffRule()],
            'default_category_id' => ['required_if:copy_categories,0'],
            'timeout' => ['integer'],
        ];
    }
}
