@extends(BaseHelper::getAdminMasterLayoutTemplate())
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="wordpress-importer">
                <h1 class="page-title">{{ trans('plugins/wordpress-importer::wordpress-importer.name') }}</h1>

                <form method="POST" action="{{ route('wordpress-importer') }}" enctype="multipart/form-data" class="import-wordpress-form">
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
                                        <ul></ul>
                                        <a class="btn btn-primary btn-small btn-loadmore" href="#">{{ trans('plugins/wordpress-importer::wordpress-importer.load_more') }}</a>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="load_seo_meta_from_yoast_seo" class="control-label">{{ trans('plugins/wordpress-importer::wordpress-importer.load_seo_meta') }}</label>
                                {{ Form::onOff('load_seo_meta_from_yoast_seo', true)  }}
                                <small class="text-muted d-block">{{ trans('plugins/wordpress-importer::wordpress-importer.load_seo_meta_description') }}</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6>{{ trans('plugins/wordpress-importer::wordpress-importer.upload_xml') }}</h6>
                            <div class="form-group">
                                <label for="timeout" class="control-label" data-toggle="tooltip" data-bs-toggle="tooltip"
                                       title="{{ trans('plugins/wordpress-importer::wordpress-importer.timeout_description') }}"
                                       data-placement="right">{{ trans('plugins/wordpress-importer::wordpress-importer.max_timeout') }}</label>
                                <input type="number" name="timeout" class="form-control" value="900" id="timeout">
                            </div>

                            <div class="form-group">
                                <label for="wpexport" class="control-label required" data-toggle="tooltip" data-bs-toggle="tooltip"
                                       title="{{ trans('plugins/wordpress-importer::wordpress-importer.wp_xml_file_description') }}"
                                       data-placement="right">{{ trans('plugins/wordpress-importer::wordpress-importer.wp_xml_file') }}</label><br>
                                <input type="file" required class="form-control" name="wpexport" id="wpexport" accept="text/xml,application/xml">
                            </div>
                            <div class="form-group">
                                <div class="alert alert-success success-message hidden"></div>

                                <div class="alert alert-warning error-message hidden"></div>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary import-wordpress-data">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>{{ trans('plugins/wordpress-importer::wordpress-importer.import') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
