<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\WordpressImporter\Exceptions\ImageDownloadException;
use Botble\WordpressImporter\Services\RemoteImageDownloader;
use Botble\WordpressImporter\Services\RemoteImageFetcher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Behaviour of the config-free parts of the fetch/download pipeline:
 * redirect resolution, response classification and upload naming.
 */
class RemoteImageFetcherBehaviourTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../src/Exceptions/ImageDownloadException.php';
        require_once __DIR__ . '/../../src/Services/RemoteImageFetcher.php';
        require_once __DIR__ . '/../../src/Services/RemoteImageDownloader.php';
    }

    private function call(object $object, string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    private function responseError(int $status, array $headers = []): ImageDownloadException
    {
        try {
            $this->call(new RemoteImageFetcher(), 'assertSuccessfulResponse', 'https://wp.test/a.jpg', $status, ["HTTP/1.1 {$status} X", ...$headers]);
        } catch (ImageDownloadException $exception) {
            return $exception;
        }

        $this->fail("HTTP {$status} must raise an ImageDownloadException.");
    }

    public function test_redirect_locations_resolve_against_the_current_url(): void
    {
        $fetcher = new RemoteImageFetcher();
        $from = 'https://wp.test:8443/wp-content/uploads/a.jpg';

        $this->assertSame('https://cdn.test/a.jpg', $this->call($fetcher, 'resolveRedirect', $from, 'https://cdn.test/a.jpg'));
        $this->assertSame('https://cdn.test/a.jpg', $this->call($fetcher, 'resolveRedirect', $from, '//cdn.test/a.jpg'));
        $this->assertSame('https://wp.test:8443/b.jpg', $this->call($fetcher, 'resolveRedirect', $from, '/b.jpg'));
        $this->assertSame('https://wp.test:8443/wp-content/uploads/c.jpg', $this->call($fetcher, 'resolveRedirect', $from, 'c.jpg'));
    }

    public function test_success_statuses_pass(): void
    {
        $this->call(new RemoteImageFetcher(), 'assertSuccessfulResponse', 'https://wp.test/a.jpg', 200, ['HTTP/1.1 200 OK']);

        $this->addToAssertionCount(1);
    }

    public function test_cloudflare_challenge_is_explained_and_blocks_the_host(): void
    {
        $exception = $this->responseError(403, ['cf-mitigated: challenge']);

        $this->assertStringContainsString('Cloudflare', $exception->getMessage());
        $this->assertStringContainsString('wp.test', $exception->getMessage());
        $this->assertFalse($exception->retryable, 'A challenge fails the same way every retry.');
        $this->assertTrue($exception->blocksHost, 'Every other image on the host will be challenged too.');
    }

    public function test_client_errors_are_permanent_and_server_errors_transient(): void
    {
        $this->assertFalse($this->responseError(404)->retryable);
        $this->assertFalse($this->responseError(404)->blocksHost);
        $this->assertTrue($this->responseError(503)->retryable);
        $this->assertTrue($this->responseError(429)->retryable);
    }

    public function test_upload_extension_comes_from_the_sniffed_mime_type(): void
    {
        $downloader = new RemoteImageDownloader(new RemoteImageFetcher());

        $this->assertSame(
            'panasonic-na-lx129el-1.jpg',
            $this->call($downloader, 'fileName', 'https://wp.test/u/Panasonic-NA-LX129EL-1.jpg', 'image/jpeg')
        );

        // Extension-less endpoints must never be stored with their script extension.
        $this->assertSame(
            'timthumb.png',
            $this->call($downloader, 'fileName', 'https://wp.test/timthumb.php?src=a.png', 'image/png')
        );

        // Long WP names keep a valid extension after truncation.
        $long = $this->call($downloader, 'fileName', 'https://wp.test/u/' . str_repeat('very-long-name-', 10) . '1920x1080.webp', 'image/webp');
        $this->assertStringEndsWith('.webp', $long);
        $this->assertLessThanOrEqual(75, strlen($long));
    }

    public function test_urls_are_percent_encoded_before_validation(): void
    {
        $this->assertSame(
            'https://site.vn/u/m%C3%A1y-gi%E1%BA%B7t%20new.jpg',
            RemoteImageFetcher::normalizeUrl('https://site.vn/u/máy-giặt new.jpg')
        );

        // Already-encoded and plain ASCII URLs are left alone.
        $this->assertSame('https://site.vn/a%20b.jpg?x=1&y=2', RemoteImageFetcher::normalizeUrl('https://site.vn/a%20b.jpg?x=1&y=2'));
        $this->assertNotFalse(filter_var(RemoteImageFetcher::normalizeUrl('https://site.vn/máy giặt.jpg'), FILTER_VALIDATE_URL));
    }
}
