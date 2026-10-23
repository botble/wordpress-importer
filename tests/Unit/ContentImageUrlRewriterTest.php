<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\WordpressImporter\Support\ContentImageUrlRewriter;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour tests for the HTML image-URL rewriter shared by the XML importer,
 * the queue path and the WooCommerce product importer.
 */
class ContentImageUrlRewriterTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../src/Support/ContentImageUrlRewriter.php';
    }

    /**
     * Resolver that "downloads" every URL by moving it under /storage/.
     */
    private function localize(): callable
    {
        return fn (string $url) => '/storage/' . basename((string) parse_url($url, PHP_URL_PATH));
    }

    public function test_rewrites_img_src(): void
    {
        $html = '<p><img class="a" src="https://wp.test/wp-content/uploads/2026/01/a.jpg" alt="A"></p>';

        $this->assertSame(
            '<p><img class="a" src="/storage/a.jpg" alt="A"></p>',
            ContentImageUrlRewriter::rewrite($html, $this->localize())
        );
    }

    public function test_rewrites_links_to_full_size_images_and_css_backgrounds(): void
    {
        $html = '<a href="https://wp.test/u/full.png"><img src="https://wp.test/u/thumb.png"></a>'
            . '<div style="background-image: url(https://wp.test/u/bg.webp)"></div>'
            . '<div style="background:url(&quot;https://wp.test/u/q.jpg&quot;)"></div>';

        $result = ContentImageUrlRewriter::rewrite($html, $this->localize());

        $this->assertStringContainsString('href="/storage/full.png"', $result);
        $this->assertStringContainsString('src="/storage/thumb.png"', $result);
        $this->assertStringContainsString('url(/storage/bg.webp)', $result);
        $this->assertStringContainsString('url(&quot;/storage/q.jpg&quot;)', $result);
        $this->assertStringNotContainsString('wp.test', $result);
    }

    public function test_drops_srcset_and_sizes_pointing_at_resized_copies(): void
    {
        $html = '<img src="https://wp.test/u/a.jpg" srcset="https://wp.test/u/a-300x200.jpg 300w, https://wp.test/u/a-1024x683.jpg 1024w" sizes="(max-width: 300px) 100vw">';

        $this->assertSame('<img src="/storage/a.jpg">', ContentImageUrlRewriter::rewrite($html, $this->localize()));
    }

    public function test_promotes_lazy_load_data_src_over_placeholder(): void
    {
        $html = '<img src="data:image/gif;base64,R0lGOD" data-src="https://wp.test/u/real.jpg" data-srcset="https://wp.test/u/real-300x200.jpg 300w">';

        $result = ContentImageUrlRewriter::rewrite($html, $this->localize());

        $this->assertStringContainsString(' src="/storage/real.jpg"', $result);
        $this->assertStringNotContainsString('data:image/gif', $result);
        $this->assertStringNotContainsString('srcset', $result);
    }

    public function test_query_string_variant_is_not_clobbered_by_bare_url(): void
    {
        $html = '<img src="https://wp.test/u/a.jpg"><img src="https://wp.test/u/a.jpg?ver=2">';
        $map = [
            'https://wp.test/u/a.jpg' => '/storage/a.jpg',
            'https://wp.test/u/a.jpg?ver=2' => '/storage/a-2.jpg',
        ];

        $this->assertSame(
            '<img src="/storage/a.jpg"><img src="/storage/a-2.jpg">',
            ContentImageUrlRewriter::rewrite($html, fn (string $url) => $map[$url] ?? null)
        );
    }

    public function test_failed_download_keeps_original_url(): void
    {
        $html = '<img src="https://wp.test/u/a.jpg">';

        $this->assertSame($html, ContentImageUrlRewriter::rewrite($html, fn () => null));
    }

    public function test_ignores_relative_and_non_image_urls(): void
    {
        $html = '<img src="/local/a.jpg"><a href="https://wp.test/about/">About</a>';
        $seen = [];

        ContentImageUrlRewriter::rewrite($html, function (string $url) use (&$seen) {
            $seen[] = $url;

            return null;
        });

        $this->assertSame([], $seen);
    }

    public function test_each_url_is_resolved_once(): void
    {
        $html = '<a href="https://wp.test/u/a.jpg"><img src="https://wp.test/u/a.jpg"></a>';
        $calls = 0;

        ContentImageUrlRewriter::rewrite($html, function () use (&$calls) {
            $calls++;

            return '/storage/a.jpg';
        });

        $this->assertSame(1, $calls);
    }

    public function test_rewrites_unencoded_utf8_and_entity_encoded_urls(): void
    {
        $html = '<img src="https://wp.test/u/máy-giặt.jpg"><img src="https://wp.test/u/a.jpg?x=1&amp;y=2">';
        $seen = [];

        $result = ContentImageUrlRewriter::rewrite($html, function (string $url) use (&$seen) {
            $seen[] = $url;

            return '/storage/' . count($seen) . '.jpg';
        });

        // UTF-8 names (kept raw by WordPress) must not be dropped, and the
        // resolver gets the real URL ("&" not "&amp;") while the HTML text is replaced.
        $this->assertSame(['https://wp.test/u/máy-giặt.jpg', 'https://wp.test/u/a.jpg?x=1&y=2'], $seen);
        $this->assertSame('<img src="/storage/1.jpg"><img src="/storage/2.jpg">', $result);
    }

    public function test_promoted_lazy_src_keeps_dollar_and_backslash_literal(): void
    {
        $html = '<img src="data:," data-src="https://wp.test/u/$1-a.jpg">';

        $this->assertSame(
            '<img src="https://wp.test/u/$1-a.jpg" data-src="https://wp.test/u/$1-a.jpg">',
            ContentImageUrlRewriter::rewrite($html, fn () => null)
        );
    }

    public function test_shorter_url_never_rewrites_the_front_of_a_longer_one(): void
    {
        $html = '<img src="https://wp.test/u/a.jpg"><img src="https://wp.test/u/a.jpg.webp"><img src="https://wp.test/u/a.jpg?v=1&amp;x=2">';

        // Only a.jpg was downloaded: the .webp and query-string variants must stay intact.
        $this->assertSame(
            '<img src="/storage/a.jpg"><img src="https://wp.test/u/a.jpg.webp"><img src="https://wp.test/u/a.jpg?v=1&amp;x=2">',
            ContentImageUrlRewriter::rewrite($html, fn (string $url) => $url === 'https://wp.test/u/a.jpg' ? '/storage/a.jpg' : null)
        );
    }

    public function test_replace_urls_matches_at_delimiters_only(): void
    {
        $map = ['https://wp.test/u/a.jpg' => '/l/a.jpg'];

        $this->assertSame(
            'url(/l/a.jpg) "/l/a.jpg" \'/l/a.jpg\' /l/a.jpg, url(&quot;/l/a.jpg&quot;) /l/a.jpg',
            ContentImageUrlRewriter::replaceUrls(
                'url(https://wp.test/u/a.jpg) "https://wp.test/u/a.jpg" \'https://wp.test/u/a.jpg\' https://wp.test/u/a.jpg, url(&quot;https://wp.test/u/a.jpg&quot;) https://wp.test/u/a.jpg',
                $map
            )
        );

        $this->assertSame('"https://wp.test/u/a.jpg-300x200.jpg"', ContentImageUrlRewriter::replaceUrls('"https://wp.test/u/a.jpg-300x200.jpg"', $map));
    }
}
