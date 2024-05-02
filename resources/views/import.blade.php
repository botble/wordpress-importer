@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <x-core::card class="wordpress-importer">
        <x-core::card.header>
            <x-core::card.title>
                {{ trans('plugins/wordpress-importer::wordpress-importer.name') }}
            </x-core::card.title>
        </x-core::card.header>
        <x-core::card.body>
            <x-core::alert type="success" class="result-message" style="display: none;" />

            {!! $form->renderForm() !!}
        </x-core::card.body>

        <x-core::card.footer>
            <x-core::button
                type="submit"
                form="wordpress-importer-form"
                icon="ti ti-file-import"
                color="primary"
            >
                {{ trans('plugins/wordpress-importer::wordpress-importer.import') }}
            </x-core::button>
        </x-core::card.footer>
    </x-core::card>
@stop
