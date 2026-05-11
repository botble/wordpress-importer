<?php

namespace Botble\WordpressImporter\Jobs;

use Botble\Base\Facades\BaseHelper;
use Botble\Blog\Models\Post;
use Botble\Media\Facades\RvMedia;
use Botble\Page\Models\Page;
use Botble\WordpressImporter\Models\WordpressImportImage;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class DownloadWordPressImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public string $originalUrl,
        public string $importId,
    ) {
        $this->onQueue((string) config('plugins.wordpress-importer.queue', 'default'));
    }

    public function handle(): void
    {
        $row = WordpressImportImage::query()
            ->where('import_id', $this->importId)
            ->where('url_hash', WordpressImportImage::hashUrl($this->originalUrl))
            ->first();

        if (! $row) {
            $row = WordpressImportImage::query()->create([
                'import_id' => $this->importId,
                'original_url' => $this->originalUrl,
                'status' => WordpressImportImage::STATUS_DOWNLOADING,
                'attempts' => 1,
            ]);
        } else {
            if ($row->status === WordpressImportImage::STATUS_DONE) {
                return;
            }

            $row->status = WordpressImportImage::STATUS_DOWNLOADING;
            $row->attempts = ($row->attempts ?? 0) + 1;
            $row->save();
        }

        try {
            $localUrl = $this->download($this->originalUrl);
        } catch (Throwable $exception) {
            $row->status = WordpressImportImage::STATUS_FAILED;
            $row->last_error = Str::limit($exception->getMessage(), 5000, '');
            $row->save();
            BaseHelper::logError($exception);

            throw $exception;
        }

        if (! $localUrl) {
            $row->status = WordpressImportImage::STATUS_FAILED;
            $row->last_error = 'Empty response or unsupported MIME type';
            $row->save();

            return;
        }

        $row->status = WordpressImportImage::STATUS_DONE;
        $row->local_url = $localUrl;
        $row->last_error = null;
        $row->save();

        $this->rewriteReferences($this->originalUrl, $localUrl);

        Cache::increment("wp-import:{$this->importId}:images_done");
    }

    protected function download(string $url): ?string
    {
        $maxBytes = (int) config('plugins.wordpress-importer.image_max_bytes', 20 * 1024 * 1024);
        $timeout = (int) config('plugins.wordpress-importer.image_timeout_seconds', 30);

        $context = stream_context_create([
            'http' => ['timeout' => $timeout, 'follow_location' => 1],
            'https' => ['timeout' => $timeout, 'follow_location' => 1],
        ]);

        $contents = @file_get_contents($url, false, $context, 0, $maxBytes);

        if (empty($contents)) {
            return null;
        }

        $info = pathinfo($url);
        $basename = $info['basename'] ?? Str::random(16);

        $path = storage_path('app/tmp/wordpress-import');
        File::ensureDirectoryExists($path);
        $tempPath = $path . '/' . Str::limit($basename, 80, '');

        try {
            file_put_contents($tempPath, $contents);
        } catch (Exception) {
            return null;
        }

        $mimeType = RvMedia::getMimeType($tempPath) ?: RvMedia::getMimeType($url);

        if (! $this->isAllowedMime($mimeType)) {
            File::delete($tempPath);

            return null;
        }

        $fileUpload = new UploadedFile($tempPath, $basename, $mimeType, null, true);

        try {
            $result = RvMedia::handleUpload($fileUpload, 0, 'posts');
        } finally {
            File::delete($tempPath);
        }

        if (! empty($result['error'])) {
            return null;
        }

        return $result['data']->url ?? null;
    }

    protected function isAllowedMime(?string $mimeType): bool
    {
        if (! $mimeType) {
            return false;
        }

        $allowed = config('core.media.media.mime_types.image', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
        ]);

        return in_array($mimeType, (array) $allowed, true);
    }

    protected function rewriteReferences(string $originalUrl, string $localUrl): void
    {
        // Either or both of blog/page may be deactivated when this job runs —
        // skip the model layer in that case and update the underlying tables
        // directly (only if the table actually exists in the schema).
        $blogActive = is_plugin_active('blog');
        $pageActive = is_plugin_active('page') || class_exists(Page::class);

        try {
            if ($blogActive && class_exists(Post::class)) {
                Post::query()
                    ->where('image', $originalUrl)
                    ->update(['image' => $localUrl]);
            }

            if ($pageActive && class_exists(Page::class)) {
                Page::query()
                    ->where('image', $originalUrl)
                    ->update(['image' => $localUrl]);
            }

            $pdo = DB::getPdo();
            $quotedOriginal = $pdo->quote($originalUrl);
            $quotedLocal = $pdo->quote($localUrl);
            $schema = DB::getSchemaBuilder();

            if ($blogActive && $schema->hasTable('posts')) {
                DB::table('posts')
                    ->where('content', 'like', '%' . $originalUrl . '%')
                    ->update([
                        'content' => DB::raw("REPLACE(content, $quotedOriginal, $quotedLocal)"),
                    ]);
            }

            if ($pageActive && $schema->hasTable('pages')) {
                DB::table('pages')
                    ->where('content', 'like', '%' . $originalUrl . '%')
                    ->update([
                        'content' => DB::raw("REPLACE(content, $quotedOriginal, $quotedLocal)"),
                    ]);
            }
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);
        }
    }
}
