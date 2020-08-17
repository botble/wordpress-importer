@extends('core/base::layouts.master')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="wordpress-importer">
                <h1 class="page-title">{{ __('Wordpress Importer') }}</h1>

                <form method="POST" action="{{ route('wordpress-importer') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ __('Options') }}</h6>
                            <div class="form-group">
                                <label for="copyimages" class="control-label">{{ __('Copy Images?') }}</label>
                                {{ Form::onOff('copyimages', true)  }}
                                <small class="text-muted d-block">{{ __('Featured images for posts and pages will be copied over to your storage. If you select "No" the image references will remain the same and no images will be copied.') }}</small>
                            </div>

                            <div class="form-group">
                                <label for="copy_categories" class="control-label">{{ __('Copy Categories?') }}</label>
                                {{ Form::onOff('copy_categories', true)  }}
                                <small class="text-muted d-block">{{ __('Categories for posts will be copied. If you uncheck you can select default category for all imported posts') }}</small>

                                <div id="category-select" class="widget meta-boxes" style="display: none">
                                    <div class="widget-title">
                                        <h4>{{ __('Select default category') }}</h4>
                                    </div>
                                    <div class="widget-body">
                                        <ul>
                                            <li>{{ __('Loading...') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="col-md-6">
                            <h6>{{ __('Upload your Wordpress XML export file below and click on Import.') }}</h6>
                            <div class="form-group">
                                <label for="timeout" class="control-label" data-toggle="tooltip"
                                       title="{{ __('When copying over posts and images from your site it may take awhile if you have a lot of data, set this to as high as you would like to prevent the script from timing out.') }}"
                                       data-placement="right">{{ __('Max Timeout in Seconds') }}</label>
                                <input type="text" name="timeout" class="form-control" value="900" id="timeout">
                            </div>

                            <div class="form-group">
                                <label for="wpexport" class="control-label required" data-toggle="tooltip"
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
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
