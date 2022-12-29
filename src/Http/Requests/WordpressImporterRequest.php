<?php

namespace Botble\WordpressImporter\Http\Requests;

use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class WordpressImporterRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'wpexport' => 'required|mimetypes:text/xml,application/xml',
            'copy_categories' => Rule::in(['0', '1']),
            'default_category_id' => 'required_if:copy_categories,0',
            'timeout' => 'integer',
        ];
    }
}
