<x-core::form.fieldset id="category-select" style="display: none">
    <h4>{{ trans('plugins/wordpress-importer::wordpress-importer.select_default_category') }}</h4>

    <div data-bb-toggle="slot-categories" class="overflow-auto" style="max-height: 20rem"></div>

    <x-core::button
        type="button"
        class="mt-3"
        data-bb-toggle="load-more"
        :data-url="route('wordpress-importer.ajax.categories')"
    >
        {{ trans('plugins/wordpress-importer::wordpress-importer.load_more') }}
    </x-core::button>
</x-core::form.fieldset>
