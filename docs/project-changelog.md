# Changelog

## 1.7.2 — 2026-10-29

Keeps the old site's URLs and taxonomy when moving from WordPress.

### Fixed
- Posts now get **every** category and tag from the export. Only the first `<category>` element was read, so extra categories were dropped and tags were created but never attached to any post.
- Pages, categories and tags keep their WordPress slug instead of a new one built from the title, so `/about` stays `/about` even when the page is titled "About Our Company".
- Category parents are linked by their WordPress slug, which no longer breaks when a category's new slug differed from its WordPress nicename.
- Percent-encoded (non-Latin) WordPress slugs are decoded before saving.
- WordPress slugs are unique per content type, but Botble serves posts, pages and categories from the same root. A clash (a page and a category both called `news`) now gets a numeric suffix (`news-1`) instead of two records answering one URL.
- Re-running an import still attaches categories and tags created by an earlier run.

## 1.7.0 — 2026-10-23

Image import overhaul for WordPress XML (posts/pages) and WooCommerce CSV (products).

### Fixed
- Featured image now comes from the post's `_thumbnail_id` instead of the last image uploaded to the post.
- Images inside content were saved as relative media paths (`posts/a.jpg`), which broke on the page. Content now gets the full public URL; image fields keep the media path.
- Imports over 25 MB (streaming parser) failed: items, authors, categories and tags could not be read.
- "Copy images" unchecked still downloaded featured images in queue mode.
- Product images in short description/description were downloaded during the validation pass as well as the import (duplicates in the media library).
- Queue mode: rewriting a URL also changed longer URLs that started with it (`a.jpg` inside `a.jpg?v=2` / `a.jpg.webp`).
- Image URLs with non-ASCII file names (e.g. `máy-giặt.jpg`) or spaces are downloaded instead of rejected.
- CLI product import could skip rows after an image failure in an earlier chunk.

### Added
- Content rewriting also covers `srcset` (removed), lazy-load `data-src` (moved to `src`), links to full-size images and CSS `url(...)` backgrounds.
- Re-running an import repairs already-imported posts, pages and products whose images still point at the old site.
- Failed product images are listed in the import results table; failed post/page images in the import summary.
- Clear error for Cloudflare-protected sources ("Blocked by Cloudflare bot protection…") with how to fix it; the rest of that host's images are skipped for the run instead of waiting on each.
- Images are downloaded once and reused across imports and CSV chunks (as long as the media file still exists).
- `WP_IMPORTER_ALLOW_PRIVATE_IMAGE_HOSTS` setting for importing from a WordPress site on a local machine/LAN.
- Translations: the 19 admin strings added in 1.6.0 (image handling, memory limit, queue and credential messages) are now translated in all 42 languages.

### Security
- Image URLs resolving to private/reserved IP addresses are refused on every redirect hop (all import paths).
- Oversized images are rejected instead of being stored truncated.
- Remote SVG files are refused (they can carry script).
- Uploaded file extension comes from the detected image type, never from the URL.

### Upgrade notes
- New migration adds `media_file_id` and a `url_hash` index to `wordpress_import_images`; runs automatically on plugin update/activation.
- Importing from `localhost`/LAN WordPress sites now requires `WP_IMPORTER_ALLOW_PRIVATE_IMAGE_HOSTS=true` in `.env`.

## 1.6.0

- Chunked imports, async (queue) image pipeline, security hardening.
