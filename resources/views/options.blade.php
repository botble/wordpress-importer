<h6>Options</h6>
<div class="form-group">
    <label for="copyimages" class="control-label">{{ __('Copy Images?') }}</label>
    {{ Form::onOff('copyimages', true, ['data-key' => 'email-config-status-btn'])  }}
    <small class="text-muted d-block">{{ __('Featured images for posts and pages will be copied over to your storage. If you select "No" the image references will remain the same and no images will be copied.') }}</small>
</div>

<div class="form-group">
    <label for="copy_categories" class="control-label">{{ __('Copy Categories?') }}</label>
    {{ Form::onOff('copy_categories', true, ['data-key' => 'email-config-status-btn'])  }}
    <small class="text-muted d-block">{{ __('Categories for posts will be copied. If you uncheck you can select default category for all imported posts') }}</small>

    <div id="category-select" class="widget meta-boxes" style="display: none">
        <div class="widget-title">
            <h4>Select default category</h4>
        </div>
        <div class="widget-body">
            <ul>
                <li>Loading...</li>
            </ul>
        </div>
    </div>
</div>
