<?php

namespace Botble\WordpressImporter\Services;

use Botble\Media\Facades\RvMedia;
use Botble\Media\Models\MediaFile;
use Botble\WordpressImporter\Exceptions\ImageDownloadException;
use Botble\WordpressImporter\Models\WordpressImportImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Single entry point for pulling a remote WordPress image into the media library.
 *
 * Used by the sync XML import, the queued DownloadWordPressImage job and the
 * WooCommerce product importer so all three share the same fetch guards
 * (RemoteImageFetcher), MIME check and de-duplication.
 *
 * Returns the media library path (e.g. "posts/a.jpg") — right for image
 * columns; wrap it in RvMedia::url() before writing it into HTML.
 */
class RemoteImageDownloader
{
    /** @var array<string, string> original URL => media path, for this run */
    protected array $cache = [];

    /** @var array<string, ImageDownloadException> original URL => failure, for this run */
    protected array $failures = [];

    /** @var array<string, ImageDownloadException> host => failure that applies to the whole host */
    protected array $blockedHosts = [];

    protected ?bool $hasImageTable = null;

    protected ?string $mediaBaseUrl = null;

    public function __construct(protected RemoteImageFetcher $fetcher = new RemoteImageFetcher())
    {
    }

    /**
     * Download $url, reusing a copy stored by an earlier import when one still
     * exists. Pass $importId to remember the result for later runs.
     *
     * @throws ImageDownloadException
     */
    public function download(string $url, string $folder = 'posts', ?string $importId = null): string
    {
        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        // Already in this site's media library (e.g. when repairing a re-imported item).
        if ($this->isOwnMediaUrl($url)) {
            return $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($failure = $this->failures[$url] ?? $this->blockedHosts[$host] ?? null) {
            throw $failure;
        }

        try {
            [$path, $mediaFileId] = $this->findStoredCopy($url) ?? $this->fetchAndStore($url, $folder);
        } catch (ImageDownloadException $exception) {
            $this->failures[$url] = $exception;

            if ($exception->blocksHost && $host !== '') {
                $this->blockedHosts[$host] = $exception;
            }

            throw $exception;
        }

        if ($importId) {
            $this->remember($url, $path, $mediaFileId, $importId);
        }

        return $this->cache[$url] = $path;
    }

    public function isHostAllowed(string $url, array $allowedHosts): bool
    {
        return $this->fetcher->isHostAllowed($url, $allowedHosts);
    }

    /**
     * @return array{0: string, 1: int|string} media path and media file ID
     *
     * @throws ImageDownloadException
     */
    protected function fetchAndStore(string $url, string $folder): array
    {
        $contents = $this->fetcher->fetch($url);

        $path = null;

        try {
            $directory = storage_path('app/tmp/wordpress-import');
            File::ensureDirectoryExists($directory);
            $path = $directory . '/' . Str::random(16);
            file_put_contents($path, $contents);

            // Detect MIME from the actual bytes (not URL extension) and require an image type.
            $mimeType = function_exists('mime_content_type') ? @mime_content_type($path) : RvMedia::getMimeType($path);

            if (! $this->isAllowedImageMime($mimeType)) {
                throw ImageDownloadException::permanent('Disallowed MIME type: ' . ($mimeType ?: 'unknown'));
            }

            $file = new UploadedFile($path, $this->fileName($url, $mimeType), $mimeType, null, true);
            $result = RvMedia::handleUpload($file, 0, $folder);
        } catch (ImageDownloadException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ImageDownloadException::transient($throwable->getMessage());
        } finally {
            if ($path) {
                File::delete($path);
            }
        }

        if (! empty($result['error']) || empty($result['data']->url)) {
            $message = $result['message'] ?? 'Upload error';

            throw ImageDownloadException::permanent(is_array($message) ? implode(', ', $message) : (string) $message);
        }

        return [$result['data']->url, $result['data']->id];
    }

    /**
     * Name the upload after the URL but take the extension from the sniffed bytes:
     * long WP names would lose their extension to truncation, and extension-less
     * endpoints (timthumb.php?src=…) must not be stored as .php.
     */
    protected function fileName(string $url, string $mimeType): string
    {
        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        $name = Str::limit(Str::slug($name) ?: Str::random(16), 70, '');
        $extension = Arr::first((new MimeTypes())->getExtensions($mimeType)) ?: 'jpg';

        return $name . '.' . $extension;
    }

    protected function isAllowedImageMime(?string $mimeType): bool
    {
        // SVG can carry script and core doesn't sanitize it — never accept it
        // from a remote, untrusted export even if the site allows SVG uploads.
        if (! $mimeType || $mimeType === 'image/svg+xml') {
            return false;
        }

        $allowed = config('core.media.media.mime_types.image', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ]);

        return in_array($mimeType, (array) $allowed, true);
    }

    /**
     * Reuse a copy downloaded by any earlier import, as long as its media file
     * hasn't been deleted since — avoids duplicates on re-import and across
     * product CSV chunks (each chunk runs in a separate request).
     *
     * @return array{0: string, 1: int|string}|null
     */
    protected function findStoredCopy(string $url): ?array
    {
        if (! $this->hasImageTable()) {
            return null;
        }

        try {
            $row = WordpressImportImage::query()
                ->where('url_hash', WordpressImportImage::hashUrl($url))
                ->where('status', WordpressImportImage::STATUS_DONE)
                ->whereNotNull('media_file_id')
                ->latest('id')
                ->first(['local_url', 'media_file_id']);

            if ($row && MediaFile::query()->whereKey($row->media_file_id)->exists()) {
                return [$row->local_url, $row->media_file_id];
            }
        } catch (Throwable) {
            // Table not migrated to the latest shape yet — just download again.
        }

        return null;
    }

    protected function remember(string $url, string $path, int|string $mediaFileId, string $importId): void
    {
        if (! $this->hasImageTable()) {
            return;
        }

        try {
            WordpressImportImage::query()->updateOrCreate(
                ['import_id' => $importId, 'url_hash' => WordpressImportImage::hashUrl($url)],
                [
                    'original_url' => $url,
                    'local_url' => $path,
                    'media_file_id' => $mediaFileId,
                    'status' => WordpressImportImage::STATUS_DONE,
                    'last_error' => null,
                ]
            );
        } catch (Throwable) {
            // Remembering is an optimisation; the image itself is already stored.
        }
    }

    protected function isOwnMediaUrl(string $url): bool
    {
        if ($this->mediaBaseUrl === null) {
            try {
                $probe = 'wp-importer-probe.jpg';
                $this->mediaBaseUrl = Str::beforeLast(RvMedia::url($probe), $probe);
            } catch (Throwable) {
                $this->mediaBaseUrl = '';
            }
        }

        return Str::startsWith($this->mediaBaseUrl, ['http://', 'https://'])
            && Str::startsWith($url, $this->mediaBaseUrl);
    }

    protected function hasImageTable(): bool
    {
        return $this->hasImageTable ??= Schema::hasTable('wordpress_import_images');
    }
}
