<?php

namespace Botble\WordpressImporter\Http\Requests;

use Botble\Base\Rules\OnOffRule;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class WordpressImporterRequest extends Request
{
    public function rules(): array
    {
        $rules = [
            'wpexport' => ['required', 'mimetypes:text/xml,application/xml'],
            'timeout' => ['integer', 'min:30', 'max:86400'],
            // memory_limit is poked into ini_set; lock it to a strict allow-list.
            'memory_limit' => ['nullable', Rule::in(['512M', '1024M', '2048M', '4096M'])],
            // image_mode toggles the SSRF/queue gate; reject anything outside the supported triplet.
            'image_mode' => ['nullable', Rule::in(['sync', 'external', 'queue'])],
        ];

        if (is_plugin_active('blog')) {
            $rules['copy_categories'] = [new OnOffRule()];
            $rules['default_category_id'] = ['required_if:copy_categories,0'];
        }

        return $rules;
    }
}
