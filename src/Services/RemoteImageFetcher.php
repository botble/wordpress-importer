<?php

namespace Botble\WordpressImporter\Services;

use Botble\WordpressImporter\Exceptions\ImageDownloadException;

/**
 * Fetches remote image bytes with the importer's SSRF guards: http(s) only,
 * host allow-list, public IPs only, bounded timeout and size. Redirects are
 * followed by hand so every hop is re-checked — PHP's follow_location would
 * let a public host bounce the request to an internal address.
 */
class RemoteImageFetcher
{
    protected const MAX_REDIRECTS = 3;

    /**
     * Browser-like headers: many WordPress hosts (security plugins, hotlink
     * protection) reject PHP's default user agent or requests without a Referer.
     */
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    /**
     * @throws ImageDownloadException
     */
    public function fetch(string $url): string
    {
        $maxBytes = (int) config('plugins.wordpress-importer.image_max_bytes', 20 * 1024 * 1024);
        $url = static::normalizeUrl($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->assertFetchable($url);

            [$status, $headers, $body] = $this->request($url, $maxBytes);

            if ($status >= 300 && $status < 400 && ($location = $this->header($headers, 'location'))) {
                $url = static::normalizeUrl($this->resolveRedirect($url, $location));

                continue;
            }

            $this->assertSuccessfulResponse($url, $status, $headers);

            if ($body === '') {
                throw ImageDownloadException::permanent('Empty response');
            }

            // We read one byte past the cap: more than that means the file was cut off.
            if (strlen($body) > $maxBytes) {
                throw ImageDownloadException::permanent(sprintf('Image is larger than %d bytes', $maxBytes));
            }

            return $body;
        }

        throw ImageDownloadException::permanent('Too many redirects');
    }

    /**
     * WordPress keeps UTF-8 upload names ("máy-giặt.jpg") and exports them raw;
     * percent-encode anything outside printable ASCII (incl. spaces) so the URL
     * validates and the request line is legal. Existing %XX escapes are untouched.
     */
    public static function normalizeUrl(string $url): string
    {
        return preg_replace_callback('/[^\x21-\x7E]+/', fn (array $matches) => rawurlencode($matches[0]), trim($url)) ?? $url;
    }

    /**
     * @throws ImageDownloadException
     */
    protected function assertFetchable(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw ImageDownloadException::permanent('Invalid URL');
        }

        if (! $this->isHostAllowed($url, (array) config('plugins.wordpress-importer.allowed_image_hosts', ['*']))) {
            throw ImageDownloadException::permanent('Host not allowed');
        }

        if (config('plugins.wordpress-importer.allow_private_image_hosts', false)) {
            return;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw ImageDownloadException::transient("Could not resolve {$host}", true);
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ImageDownloadException::permanent("{$host} resolves to a private or reserved address", true);
        }
    }

    /**
     * @return array{0: int, 1: string[], 2: string} status, headers, body
     *
     * @throws ImageDownloadException
     */
    protected function request(string $url, int $maxBytes): array
    {
        $origin = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/';

        $options = [
            'timeout' => (int) config('plugins.wordpress-importer.image_timeout_seconds', 30),
            'follow_location' => 0,
            // Read 4xx/5xx bodies too so the status can be reported instead of a bare "failed".
            'ignore_errors' => true,
            'user_agent' => self::USER_AGENT,
            'header' => "Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8\r\nReferer: {$origin}\r\n",
        ];

        $context = stream_context_create(['http' => $options, 'https' => $options]);

        $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
        $headers = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);

        if ($body === false) {
            // error_get_last() is empty under Laravel's error handler, so name the likely causes.
            throw ImageDownloadException::transient(sprintf(
                'Could not connect to %s (DNS, timeout or TLS error)',
                parse_url($url, PHP_URL_HOST)
            ), true);
        }

        $status = preg_match('#^HTTP/\S+\s+(\d{3})#', $headers[0] ?? '', $matches) ? (int) $matches[1] : 0;

        return [$status, $headers, $body];
    }

    /**
     * @throws ImageDownloadException
     */
    protected function assertSuccessfulResponse(string $url, int $status, array $headers): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($this->header($headers, 'cf-mitigated') !== null) {
            throw ImageDownloadException::permanent(sprintf(
                'Blocked by Cloudflare bot protection on %s (HTTP %d). Allow this server\'s IP in Cloudflare (Security > WAF > Tools) or pause protection while importing, then import again.',
                $host,
                $status
            ), true);
        }

        $message = "HTTP {$status} from {$host}";

        throw $status === 429 || $status >= 500
            ? ImageDownloadException::transient($message)
            : ImageDownloadException::permanent($message);
    }

    protected function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, $name . ':') === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }

    protected function resolveRedirect(string $from, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $scheme = parse_url($from, PHP_URL_SCHEME);

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        $port = parse_url($from, PHP_URL_PORT);
        $origin = $scheme . '://' . parse_url($from, PHP_URL_HOST) . ($port ? ':' . $port : '');

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = (string) parse_url($from, PHP_URL_PATH);

        return $origin . rtrim(str_contains($path, '/') ? substr($path, 0, strrpos($path, '/') + 1) : '/', '/') . '/' . $location;
    }

    public function isHostAllowed(string $url, array $allowedHosts): bool
    {
        if (in_array('*', $allowedHosts, true)) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        foreach ($allowedHosts as $allowed) {
            if (! is_string($allowed) || $allowed === '') {
                continue;
            }
            if (strcasecmp($host, $allowed) === 0) {
                return true;
            }
            if (str_starts_with($allowed, '*.') && str_ends_with(strtolower($host), strtolower(substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
    }
}
