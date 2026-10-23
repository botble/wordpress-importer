<?php

namespace Botble\WordpressImporter\Jobs;

use Botble\Base\Facades\BaseHelper;
use Botble\Blog\Models\Post;
use Botble\Media\Facades\RvMedia;
use Botble\Page\Models\Page;
use Botble\WordpressImporter\Exceptions\ImageDownloadException;
use Botble\WordpressImporter\Models\WordpressImportImage;
use Botble\WordpressImporter\Services\RemoteImageDownloader;
use Botble\WordpressImporter\Support\ContentImageUrlRewriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $localUrl = (new RemoteImageDownloader())->download($this->originalUrl, 'posts', $this->importId);
        } catch (ImageDownloadException $exception) {
            $row->status = WordpressImportImage::STATUS_FAILED;
            $row->last_error = Str::limit($exception->getMessage(), 5000, '');
            $row->save();

            // Only transient failures (timeouts, 5xx, 429) are worth the backoff —
            // a bad MIME or a WAF challenge will fail the same way every time.
            if ($exception->retryable) {
                BaseHelper::logError($exception);

                throw $exception;
            }

            return;
        } catch (Throwable $throwable) {
            // Never leave the row stuck in "downloading" — mark it and let the queue retry.
            $row->status = WordpressImportImage::STATUS_FAILED;
            $row->last_error = Str::limit($throwable->getMessage(), 5000, '');
            $row->save();
            BaseHelper::logError($throwable);

            throw $throwable;
        }

        $row->status = WordpressImportImage::STATUS_DONE;
        $row->local_url = $localUrl;
        $row->last_error = null;
        $row->save();

        $this->rewriteReferences($this->originalUrl, $localUrl);

        Cache::increment("wp-import:{$this->importId}:images_done");
    }

    /**
     * Rewrite in PHP rather than SQL REPLACE(): REPLACE() also hits URLs that
     * merely start with this one ("a.jpg" inside "a.jpg?v=2" / "a.jpg.webp"),
     * corrupting them when another job localizes the longer URL.
     */
    protected function rewriteContent(string $table, string $originalUrl, array $replacements): void
    {
        // LIKE on the part before any query string also matches the "&amp;" form;
        // it is only a pre-filter — replaceUrls() decides what actually changes.
        $needle = addcslashes(Str::before($originalUrl, '?'), '\\%_');

        DB::table($table)
            ->where('content', 'like', '%' . $needle . '%')
            ->select(['id', 'content'])
            ->chunkById(100, function ($rows) use ($table, $replacements): void {
                foreach ($rows as $row) {
                    $content = ContentImageUrlRewriter::replaceUrls((string) $row->content, $replacements);

                    if ($content !== $row->content) {
                        DB::table($table)->where('id', $row->id)->update(['content' => $content]);
                    }
                }
            });
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

            // Columns hold the media path; HTML needs the public URL. Inside HTML
            // attributes a query string's "&" is stored as "&amp;" — replace both.
            $publicUrl = RvMedia::url($localUrl);
            $replacements = [
                $originalUrl => $publicUrl,
                htmlspecialchars($originalUrl, ENT_NOQUOTES) => $publicUrl,
            ];

            $schema = DB::getSchemaBuilder();

            foreach (['posts' => $blogActive, 'pages' => $pageActive] as $table => $active) {
                if ($active && $schema->hasTable($table)) {
                    $this->rewriteContent($table, $originalUrl, $replacements);
                }
            }
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);
        }
    }
}
