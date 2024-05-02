<?php

namespace Botble\WordpressImporter\Forms;

use Botble\Base\Forms\FieldOptions\HtmlFieldOption;
use Botble\Base\Forms\FieldOptions\NumberFieldOption;
use Botble\Base\Forms\FieldOptions\OnOffFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\HtmlField;
use Botble\Base\Forms\Fields\NumberField;
use Botble\Base\Forms\Fields\OnOffField;
use Botble\Base\Forms\FormAbstract;

class WordpressImporterForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->setFormOptions(['id' => 'wordpress-importer-form', 'class' => 'import-wordpress-form'])
            ->contentOnly()
            ->setUrl(route('wordpress-importer'))
            ->hasFiles()
            ->columns()
            ->add(
                'open_left_column',
                HtmlField::class,
                HtmlFieldOption::make()->content('<div class="col-12">')
            )
            ->add(
                'left_column_heading',
                HtmlField::class,
                HtmlFieldOption::make()->content('<h4>' . trans('plugins/wordpress-importer::wordpress-importer.options') . '</h4>')
            )
            ->add(
                'copyimages',
                OnOffField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/wordpress-importer::wordpress-importer.copy_images'))
                    ->helperText(trans('plugins/wordpress-importer::wordpress-importer.copy_images_description'))
                    ->defaultValue(true)
            )
            ->when(is_plugin_active('blog'), function (FormAbstract $form) {
                $form
                    ->add(
                        'copy_categories',
                        OnOffField::class,
                        OnOffFieldOption::make()
                            ->label(trans('plugins/wordpress-importer::wordpress-importer.copy_categories'))
                            ->helperText(trans('plugins/wordpress-importer::wordpress-importer.copy_categories_description'))
                            ->defaultValue(true)
                    )
                    ->add(
                        'category_select',
                        HtmlField::class,
                        HtmlFieldOption::make()
                            ->content(view('plugins/wordpress-importer::partials.category-select'))
                    );
            })
            ->add(
                'load_seo_meta_from_yoast_seo',
                OnOffField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/wordpress-importer::wordpress-importer.load_seo_meta'))
                    ->helperText(trans('plugins/wordpress-importer::wordpress-importer.load_seo_meta_description'))
                    ->defaultValue(true)
            )
            ->add('close_left_column', HtmlField::class, HtmlFieldOption::make()->content('</div>'))
            ->add('open_right_column', HtmlField::class, HtmlFieldOption::make()->content('<div class="col-12">'))
            ->add(
                'right_column_heading',
                HtmlField::class,
                HtmlFieldOption::make()->content('<h4>' . trans('plugins/wordpress-importer::wordpress-importer.upload_xml') . '</h4>')
            )
            ->add(
                'timeout',
                NumberField::class,
                NumberFieldOption::make()
                    ->label(trans('plugins/wordpress-importer::wordpress-importer.max_timeout'))
                    ->defaultValue(900)
                    ->helperText(trans('plugins/wordpress-importer::wordpress-importer.timeout_description'))
            )
            ->add(
                'wpexport',
                'file',
                TextFieldOption::make()
                    ->label(trans('plugins/wordpress-importer::wordpress-importer.wp_xml_file'))
                    ->helperText(trans('plugins/wordpress-importer::wordpress-importer.wp_xml_file_description'))
                    ->addAttribute('accept', 'text/xml,application/xml')
                    ->required()
            )
            ->add('close_right_column', HtmlField::class, HtmlFieldOption::make()->content('</div>'));
    }
}
