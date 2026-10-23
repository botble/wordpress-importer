<?php

namespace Botble\WordpressImporter\Support;

/**
 * Finds every image URL in WordPress HTML and swaps it for a local copy.
 *
 * Covers more than `<img src>`: links to full-size files (`<a href="…jpg">`),
 * CSS `url(…)` backgrounds and lazy-load `data-src` attributes all reference
 * the source host and would break once it goes away.
 */
class ContentImageUrlRewriter
{
    protected const IMAGE_URL_PATTERN = '#https?://[^\s"\'<>()]+?\.(?:jpe?g|png|gif|webp|bmp|svg|avif)(?:\?[^\s"\'<>()]*)?(?=[\s"\'<>(),&]|$)#i';

    /**
     * @param callable(string): ?string $resolve Returns the replacement URL, or null/the same URL to keep it.
     */
    public static function rewrite(string $html, callable $resolve): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $html = static::normalizeImageTags($html);

        $replacements = [];

        foreach (static::extractUrls($html) as $url) {
            // Attributes hold "&amp;" in query strings; fetch the real URL, replace the raw text.
            $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
            $newUrl = $resolve($decodedUrl);

            if ($newUrl && $newUrl !== $decodedUrl) {
                $replacements[$url] = $newUrl;
            }
        }

        return static::replaceUrls($html, $replacements);
    }

    /**
     * Replace whole URLs only: a URL must end at a delimiter (quote, space, ")"…),
     * so "a.jpg" never rewrites the front of "a.jpg?v=2" or "a.jpg.webp" — which
     * would leave a broken local path when only the shorter one was downloaded.
     *
     * @param array<string, string> $replacements original URL => new URL
     */
    public static function replaceUrls(string $html, array $replacements): string
    {
        $replacements = array_filter($replacements, fn ($to, $from) => $from !== '' && $from !== $to, ARRAY_FILTER_USE_BOTH);

        if (! $replacements) {
            return $html;
        }

        // Longest first; chunked to keep each compiled pattern small. Replaced
        // text is a local URL, so a later (shorter) original can't match inside it.
        uksort($replacements, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach (array_chunk($replacements, 50, true) as $chunk) {
            $pattern = '~(?:' . implode('|', array_map(fn ($url) => preg_quote($url, '~'), array_keys($chunk))) . ')(?=[\s"\'<>(),&]|$)~';
            $html = preg_replace_callback($pattern, fn (array $matches) => $chunk[$matches[0]], $html) ?? $html;
        }

        return $html;
    }

    /**
     * @return string[] Unique absolute image URLs referenced by the HTML.
     */
    public static function extractUrls(string $html): array
    {
        $urls = [];

        // <img src> can point at extension-less endpoints (CDN resizers, ?attachment_id=…).
        if (preg_match_all('/<img\b[^>]*?\ssrc\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            $urls = $matches[2];
        }

        if (preg_match_all(self::IMAGE_URL_PATTERN, $html, $matches)) {
            $urls = [...$urls, ...$matches[0]];
        }

        $urls = array_filter(
            array_map('trim', $urls),
            fn (string $url) => static::isAbsoluteUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5))
        );

        return array_values(array_unique($urls));
    }

    /**
     * Looser than FILTER_VALIDATE_URL on purpose: WordPress exports UTF-8 file
     * names unencoded, which that filter rejects. The fetcher normalizes and
     * fully validates before any request.
     */
    public static function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('~^https?://[^/\s?#]+~i', $url);
    }

    /**
     * Lazy-load plugins keep the real file in data-src and a placeholder in src —
     * promote it, since the plugin's script isn't there to do it after import.
     * srcset/sizes list WordPress-generated resized copies on the old host; drop
     * them so browsers fall back to the localized src.
     */
    protected static function normalizeImageTags(string $html): string
    {
        return preg_replace_callback('/<img\b[^>]*>/i', function (array $matches) {
            $tag = $matches[0];

            if (preg_match('/\sdata-(?:lazy-)?src\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i', $tag, $lazy)) {
                $src = ' src="' . $lazy[2] . '"';
                // Callbacks, not replacement strings: "$1" or "\\" inside a URL must stay literal.
                $tag = preg_replace_callback('/\ssrc\s*=\s*(["\']).*?\1/is', fn () => $src, $tag, 1, $count);

                if (! $count) {
                    $tag = '<img' . $src . substr($tag, 4);
                }
            }

            return preg_replace('/\s(?:data-(?:lazy-)?)?(?:srcset|sizes)\s*=\s*(["\']).*?\1/is', '', $tag);
        }, $html) ?? $html;
    }
}
