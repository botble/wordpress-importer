<x-core::alert type="info">
    <p>{{ trans('plugins/wordpress-importer::wordpress-importer.data_synchronize.import_products.export_instruction') }}</p>
    <p>{{ trans('plugins/wordpress-importer::wordpress-importer.data_synchronize.import_products.export_instruction_note') }}</p>

    @if (Route::has('tools.data-synchronize.import.products.index'))
        <p class="mb-0">
            {!! BaseHelper::clean(trans('plugins/wordpress-importer::wordpress-importer.data_synchronize.import_products.own_spreadsheet_hint', [
                'link' => Html::link(
                    route('tools.data-synchronize.import.products.index'),
                    trans('plugins/wordpress-importer::wordpress-importer.data_synchronize.import_products.own_spreadsheet_link')
                ),
            ])) !!}
        </p>
    @endif
</x-core::alert>
