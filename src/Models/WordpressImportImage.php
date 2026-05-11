<?php

namespace Botble\WordpressImporter\Models;

use Botble\Base\Models\BaseModel;

class WordpressImportImage extends BaseModel
{
    protected $table = 'wordpress_import_images';

    protected $fillable = [
        'import_id',
        'original_url',
        'url_hash',
        'local_url',
        'status',
        'attempts',
        'last_error',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_DOWNLOADING = 'downloading';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (empty($model->url_hash) && ! empty($model->original_url)) {
                $model->url_hash = sha1((string) $model->original_url);
            }
        });
    }

    public static function hashUrl(string $url): string
    {
        return sha1($url);
    }
}
