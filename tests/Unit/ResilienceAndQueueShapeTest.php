<?php

namespace Botble\WordpressImporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 02 (import resilience) and Phase 03 (async image pipeline) shape locks.
 *
 * These read the source verbatim and assert the structural patterns are present.
 * Behavior-level integration tests would need a live MySQL + Laravel bootstrap
 * with the plugin activated — out of scope for the standalone test suite.
 *
 * @see plans/260511-1522-wordpress-importer-improvements/phase-02-import-resilience.md
 * @see plans/260511-1522-wordpress-importer-improvements/phase-03-async-image-pipeline.md
 */
class ResilienceAndQueueShapeTest extends TestCase
{
    private const IMPORTER_PATH = __DIR__ . '/../../src/WordpressImporter.php';
    private const CONTROLLER_PATH = __DIR__ . '/../../src/Http/Controllers/WordpressImporterController.php';
    private const FORM_PATH = __DIR__ . '/../../src/Forms/WordpressImporterForm.php';
    private const JOB_PATH = __DIR__ . '/../../src/Jobs/DownloadWordPressImage.php';
    private const ROUTES_PATH = __DIR__ . '/../../routes/web.php';
    private const CONFIG_PATH = __DIR__ . '/../../config/wordpress-importer.php';

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source, "Source must be readable: {$path}");

        return $source;
    }

    // -- Phase 02 --------------------------------------------------------

    public function test_chunk_size_is_configurable_with_safe_default(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/config\(\s*[\'"]plugins\.wordpress-importer\.chunk_size[\'"]\s*,\s*50\s*\)/',
            $source,
            'savePostsAndPages() must read chunk size from config with a sensible default (50).'
        );
    }

    public function test_each_chunk_is_wrapped_in_a_database_transaction(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/processItemChunk\([\s\S]*?DB::transaction/s',
            $source,
            'processItemChunk() must wrap each chunk in DB::transaction so a poison row does not corrupt earlier ones.'
        );
    }

    public function test_streaming_mode_does_not_buffer_simplexml_nodes(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        // The streaming branch must use processSingleItem (per-row), not chunkBuffer (which retains DOM subtrees).
        $this->assertMatchesRegularExpression(
            '/useStreamingParser[\s\S]*?streamItems\(\)[\s\S]*?processSingleItem/s',
            $source,
            'Streaming path must process items one at a time (no chunkBuffer) to release DOM subtrees.'
        );
    }

    public function test_xml_reader_calls_next_after_expand(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/\$reader->next\(\)/',
            $source,
            'XMLReader must advance with $reader->next() after $reader->expand(), otherwise the DOM subtree is retained.'
        );
    }

    public function test_imported_ids_are_preloaded_in_a_single_query(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/protected function preloadImportedIds\(/',
            $source,
            'preloadImportedIds() is required — single query at pass start avoids the N+1 on meta_boxes for 1k+ rows.'
        );

        // savePostsAndPages() resolves $modelClass/$metaKey from the $type arg
        // then calls preloadImportedIds — assert both branches and the call.
        $this->assertStringContainsString(
            "'_wp_import_post_id'",
            $source,
            'Posts pass must use the _wp_import_post_id meta key.'
        );

        $this->assertStringContainsString(
            "'_wp_import_page_id'",
            $source,
            'Pages pass must use the _wp_import_page_id meta key.'
        );

        $this->assertMatchesRegularExpression(
            '/\$this->preloadImportedIds\(\s*\$modelClass\s*,\s*\$metaKey\s*\)/',
            $source,
            'savePostsAndPages() must preload IDs once at pass start before iterating.'
        );
    }

    public function test_emit_progress_writes_to_cache_with_short_ttl(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/Cache::put\([\s\S]*?addMinutes\(\s*30\s*\)/',
            $source,
            'emitProgress() must use a bounded cache TTL (30 minutes) so abandoned imports do not leak keys.'
        );
    }

    public function test_row_errors_are_collected_and_returned(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/protected\s+array\s+\$rowErrors\b/',
            $source,
            '$rowErrors collector is required for per-row failure reporting.'
        );

        $this->assertStringContainsString(
            "'row_errors' => \$this->rowErrors",
            $source,
            'Import result must include row_errors so the controller can surface them.'
        );
    }

    public function test_streaming_threshold_falls_back_to_25mb(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/streaming_threshold_bytes[\'"][\s\S]*?25 \* 1024 \* 1024/',
            $source,
            'Streaming threshold default must be 25 MB so small/medium exports keep using SimpleXMLElement.'
        );
    }

    public function test_progress_route_is_registered_with_safe_constraint(): void
    {
        $source = $this->read(self::ROUTES_PATH);

        $this->assertStringContainsString(
            "'wordpress-importer.progress'",
            $source,
            'GET .../progress/{key} route is required for the UI poll loop.'
        );

        // Constraint prevents arbitrary user-supplied cache key reads.
        $this->assertMatchesRegularExpression(
            '/where\(\s*[\'"]key[\'"]\s*,\s*[\'"]wp-import:[^\'"]+[\'"]/',
            $source,
            'Progress route must constrain {key} to the wp-import: prefix.'
        );
    }

    // -- Phase 03 --------------------------------------------------------

    public function test_image_mode_field_offers_three_choices(): void
    {
        $source = $this->read(self::FORM_PATH);

        $this->assertStringContainsString("'image_mode'", $source);
        $this->assertStringContainsString("'sync'", $source);
        $this->assertStringContainsString("'external'", $source);
        $this->assertStringContainsString("'queue'", $source);
    }

    public function test_job_uses_3_tries_and_exponential_backoff(): void
    {
        $source = $this->read(self::JOB_PATH);

        $this->assertMatchesRegularExpression(
            '/public\s+int\s+\$tries\s*=\s*3/',
            $source,
            'Job must retry up to 3 times.'
        );

        $this->assertMatchesRegularExpression(
            '/public\s+array\s+\$backoff\s*=\s*\[\s*30\s*,\s*120\s*,\s*600\s*\]/',
            $source,
            'Exponential backoff must be [30, 120, 600] — respects upstream rate limits.'
        );
    }

    public function test_job_validates_mime_type_before_upload(): void
    {
        $source = $this->read(self::JOB_PATH);

        $this->assertMatchesRegularExpression(
            '/protected function isAllowedMime\(/',
            $source,
            'isAllowedMime() guard is required — without it, queue mode would let an attacker upload any MIME to RvMedia.'
        );
    }

    public function test_job_rewrites_post_and_page_content(): void
    {
        $source = $this->read(self::JOB_PATH);

        $this->assertStringContainsString(
            "DB::table('posts')",
            $source,
            'rewriteReferences() must update posts.content so inline <img src> URLs are swapped.'
        );

        $this->assertStringContainsString(
            "DB::table('pages')",
            $source,
            'rewriteReferences() must also update pages.content (pages share the WP content schema).'
        );
    }

    public function test_job_uses_pdo_quote_for_replace_arguments(): void
    {
        $source = $this->read(self::JOB_PATH);

        // PDO::quote is the injection-safe escape for REPLACE() arguments — see code review §H.
        $this->assertMatchesRegularExpression(
            '/\$pdo->quote\(\$originalUrl\)/',
            $source,
            'REPLACE() must escape its arguments with PDO::quote, not string interpolation.'
        );
    }

    public function test_controller_exposes_retry_failed_endpoint(): void
    {
        $controller = $this->read(self::CONTROLLER_PATH);
        $routes = $this->read(self::ROUTES_PATH);

        $this->assertMatchesRegularExpression(
            '/public function retryFailedImages\(/',
            $controller,
            'retryFailedImages controller method is required.'
        );

        $this->assertStringContainsString(
            "'wordpress-importer.images.retry'",
            $routes,
            'Retry route must be registered under the admin-permission group.'
        );
    }

    public function test_controller_exposes_image_status_endpoint(): void
    {
        $controller = $this->read(self::CONTROLLER_PATH);

        $this->assertMatchesRegularExpression(
            '/public function imageStatus\(/',
            $controller,
            'imageStatus controller method is required.'
        );
    }

    public function test_credentials_are_not_in_http_response(): void
    {
        $controller = $this->read(self::CONTROLLER_PATH);

        $this->assertStringNotContainsString(
            "'generated_passwords'",
            $controller,
            'Generated passwords must NOT be returned in the JSON response — only a count + one-shot download URL.'
        );

        $this->assertStringContainsString(
            "'credentials_download_url'",
            $controller,
            'Controller must expose a one-shot download URL for the credentials CSV.'
        );
    }

    public function test_config_file_has_all_expected_keys(): void
    {
        $source = $this->read(self::CONFIG_PATH);

        foreach (['chunk_size', 'streaming_threshold_bytes', 'queue', 'image_concurrency', 'image_timeout_seconds', 'image_max_bytes', 'allowed_image_hosts'] as $key) {
            $this->assertMatchesRegularExpression(
                "/[\\'\"]" . preg_quote($key, '/') . "[\\'\"]\\s*=>/",
                $source,
                "config('plugins.wordpress-importer.{$key}') must be defined."
            );
        }
    }

    public function test_pre_flight_warns_when_queue_default_is_sync(): void
    {
        $controller = $this->read(self::CONTROLLER_PATH);

        $this->assertMatchesRegularExpression(
            '/image_mode[\s\S]*?queue[\s\S]*?config\(\s*[\'"]queue\.default[\'"][\s\S]*?sync/s',
            $controller,
            "Pre-flight check is required: warn admin when image_mode='queue' but queue.default='sync'."
        );
    }
}
