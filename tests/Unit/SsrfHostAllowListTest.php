<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\WordpressImporter\WordpressImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for the SSRF allow-list applied to image URLs in `image_mode=queue`.
 *
 * The allow-list lives in WordpressImporter::isHostAllowed() and supports three
 * patterns: '*' wildcard (default), exact hostname, '*.subdomain' wildcard.
 *
 * @see platform/plugins/wordpress-importer/src/WordpressImporter.php
 */
class SsrfHostAllowListTest extends TestCase
{
    private WordpressImporter $importer;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        // Loading the file standalone — class autoload isn't wired without an
        // activated plugin, but require_once works because the class body never
        // touches its dependencies at construct time.
        require_once __DIR__ . '/../../src/WordpressImporter.php';

        $this->importer = new WordpressImporter();
        $this->method = new ReflectionMethod($this->importer, 'isHostAllowed');
        $this->method->setAccessible(true);
    }

    private function isAllowed(string $url, array $allowedHosts): bool
    {
        return (bool) $this->method->invoke($this->importer, $url, $allowedHosts);
    }

    public function test_wildcard_allows_everything(): void
    {
        $this->assertTrue($this->isAllowed('https://example.com/img.png', ['*']));
        $this->assertTrue($this->isAllowed('https://sub.example.com/img.png', ['*']));
        $this->assertTrue($this->isAllowed('http://attacker.example/img.png', ['*']));
    }

    public function test_exact_host_match(): void
    {
        $this->assertTrue($this->isAllowed('https://example.com/img.png', ['example.com']));
        $this->assertFalse($this->isAllowed('https://attacker.example/img.png', ['example.com']));
    }

    public function test_exact_host_is_case_insensitive(): void
    {
        $this->assertTrue($this->isAllowed('https://Example.COM/img.png', ['example.com']));
        $this->assertTrue($this->isAllowed('https://example.com/img.png', ['EXAMPLE.com']));
    }

    public function test_subdomain_wildcard_matches_only_subdomains(): void
    {
        $allowed = ['*.example.com'];

        // Matches subdomains
        $this->assertTrue($this->isAllowed('https://cdn.example.com/img.png', $allowed));
        $this->assertTrue($this->isAllowed('https://media.example.com/img.png', $allowed));
        $this->assertTrue($this->isAllowed('https://a.b.example.com/img.png', $allowed));

        // Apex by itself is also accepted because '*.example.com' ends with '.example.com'
        // when host is 'example.com' — the implementation uses str_ends_with on the
        // host against the wildcard's tail. Document the actual behavior.
        $this->assertFalse(
            $this->isAllowed('https://example.com/img.png', $allowed),
            'Apex domain is NOT covered by *.example.com (must list "example.com" separately if intended).'
        );

        // Does not match unrelated domains
        $this->assertFalse($this->isAllowed('https://attacker.example/img.png', $allowed));
        $this->assertFalse(
            $this->isAllowed('https://malicious-example.com/img.png', $allowed),
            'Bare suffix match on hyphenated lookalike must be rejected.'
        );
    }

    public function test_empty_allow_list_denies_everything(): void
    {
        $this->assertFalse($this->isAllowed('https://example.com/img.png', []));
        $this->assertFalse($this->isAllowed('https://anywhere.test/img.png', []));
    }

    public function test_malformed_urls_are_denied_when_allow_list_is_set(): void
    {
        // parse_url returns false / no host — must not crash, must deny.
        // (Wildcard '*' short-circuits the check intentionally; SSRF protection
        // is opt-in via a real allow-list.)
        $this->assertFalse($this->isAllowed('not a url', ['example.com']));
        $this->assertFalse($this->isAllowed('http:///path-only', ['example.com']));
        $this->assertFalse($this->isAllowed('', ['example.com']));
    }

    public function test_wildcard_intentionally_skips_url_validation(): void
    {
        // Documents the deliberate trade-off: '*' allow-list means "trust input".
        // Tightening requires the operator to configure WP_IMPORTER_ALLOWED_IMAGE_HOSTS.
        $this->assertTrue($this->isAllowed('not a url', ['*']));
    }

    public function test_combined_allow_list(): void
    {
        $allowed = ['cdn.example.com', '*.example.org', 'example.com'];

        $this->assertTrue($this->isAllowed('https://cdn.example.com/img.png', $allowed));
        $this->assertTrue($this->isAllowed('https://media.example.org/img.png', $allowed));
        $this->assertTrue($this->isAllowed('https://example.com/img.png', $allowed));

        $this->assertFalse($this->isAllowed('https://example.org/img.png', $allowed));
        $this->assertFalse($this->isAllowed('https://attacker.example/img.png', $allowed));
    }
}
