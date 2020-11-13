@extends('core/base::layouts.master')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="wordpress-importer">
                <h1 class="page-title">{{ trans('plugins/wordpress-importer::wordpress-importer.name') }}</h1>

                <form method="POST" action="{{ route('wordpress-importer') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ trans('plugins/wordpress-importer::wordpress-importer.options') }}</h6>
                            <div class="form-group">
                                <label for="copyimages" class="control-label">{{ trans('plugins/wordpress-importer::wordpress-importer.copy_images') }}</label>
                                {{ Form::onOff('copyimages', true)  }}
                                <small class="text-muted d-block">{{ trans('plugins/wordpress-importer::wordpress-importer.copy_images_description') }}</small>
                            </div>

                            <div class="form-group">
                                <label for="copy_categories" class="control-label">{{ trans('plugins/wordpress-importer::wordpress-importer.copy_categories') }}</label>
                                {{ Form::onOff('copy_categories', true)  }}
                                <small class="text-muted d-block">{{ trans('plugins/wordpress-importer::wordpress-importer.copy_categories_description') }}</small>

                                <div id="category-select" class="widget meta-boxes" style="display: none">
                                    <div class="widget-title">
                                        <h4>{{ trans('plugins/wordpress-importer::wordpress-importer.select_default_category') }}</h4>
                                    </div>
                                    <div class="widget-body">
                                        <ul>
                                            <li>{{ trans('plugins/wordpress-importer::wordpress-importer.loading') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="col-md-6">
                            <h6>{{ trans('plugins/wordpress-importer::wordpress-importer.upload_xml') }}</h6>
                            <div class="form-group">
                                <label for="timeout" class="control-label" data-toggle="tooltip"
                                       title="{{ trans('plugins/wordpress-importer::wordpress-importer.timeout_description') }}"
                                       data-placement="right">{{ trans('plugins/wordpress-importer::wordpress-importer.max_timeout') }}</label>
                                <input type="text" name="timeout" class="form-control" value="900" id="timeout">
                            </div>

                            <div class="form-group">
                                <label for="wpexport" class="control-label required" data-toggle="tooltip"
                                       title="{{ trans('plugins/wordpress-importer::wordpress-importer.wp_xml_file_description') }}"
                                       data-placement="right">{{ trans('plugins/wordpress-importer::wordpress-importer.wp_xml_file') }}</label><br>
                                <input type="file" name="wpexport" id="wpexport">
                            </div>
                            <div class="form-group">
                                <div class="alert alert-success success-message hidden"></div>

                                <div class="alert alert-warning error-message hidden"></div>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary import-wordpress-data">{{ trans('plugins/wordpress-importer::wordpress-importer.import') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
