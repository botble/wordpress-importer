@extends('core/base::layouts.master')

@section('content')
    <div class="wordpress-importer" style="max-width: 700px">
        <h1 class="page-title">{{ __('Wordpress Importer') }}</h1>
        <p>{{ __('Upload your Wordpress XML export file below and click on Import.') }}</p>

        <form method="POST" action="{{ route('wordpress-importer') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="copyimages" class="control-label" data-toggle="tooltip"
                       title="{{ __('Featured images for posts and pages will be copied over to your storage. If you select "No" the image references will remain the same and no images will be copied.') }}"
                       data-placement="right"><input type="checkbox" name="copyimages" id="copyimages" checked>{{ __('Copy Images?') }}</label>
            </div>

            <div class="form-group">
                <label for="timeout" class="control-label" data-toggle="tooltip"
                       title="{{ __('When copying over posts and images from your site it may take awhile if you have a lot of data, set this to as high as you would like to prevent the script from timing out.') }}"
                       data-placement="right">{{ __('Max Timeout in Seconds') }}</label>
                <input type="text" name="timeout" class="form-control" value="900" id="timeout">
            </div>

            <div class="form-group">
                <label for="wpexport" class="control-label" data-toggle="tooltip"
                       title="{{ __('Inside of your Wordpress Admin you can chose to export data by visiting Tools->Export.') }}"
                       data-placement="right">{{ __('Wordpress XML file') }}</label><br>
                <input type="file" name="wpexport" id="wpexport">
            </div>
            <div class="form-group">
                <div class="alert alert-success success-message hidden"></div>

                <div class="alert alert-warning error-message hidden"></div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary import-wordpress-data">{{ __('Import') }}</button>
            </div>
        </form>
    </div>
@stop
