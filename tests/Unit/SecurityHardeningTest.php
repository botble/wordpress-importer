<?php

namespace Botble\WordpressImporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Locks the security hardening applied to address the audit findings:
 *
 *  - C-1: SSRF gate (host + timeout + max-bytes + MIME) in the sync image path
 *  - C-2: XML entity-expansion / external-entity protections + memory_limit cap
 *  - C-3: Admin category-picker uses DOM construction (no innerHTML interpolation)
 *  - H-2: WordpressImporterRequest validates memory_limit + image_mode
 *  - H-3: DownloadWordPressImage job guards against inactive blog/page plugins
 *  - H-4: imageStatus aggregates counts in SQL (no in-PHP hydration)
 *
 * @see plans/reports/audit-260511-1557-wordpress-importer-plugin.md
 */
class SecurityHardeningTest extends TestCase
{
    private const IMPORTER_PATH = __DIR__ . '/../../src/WordpressImporter.php';
    private const FORM_PATH = __DIR__ . '/../../src/Forms/WordpressImporterForm.php';
    private const REQUEST_PATH = __DIR__ . '/../../src/Http/Requests/WordpressImporterRequest.php';
    private const CONTROLLER_PATH = __DIR__ . '/../../src/Http/Controllers/WordpressImporterController.php';
    private const JOB_PATH = __DIR__ . '/../../src/Jobs/DownloadWordPressImage.php';
    private const JS_PATH = __DIR__ . '/../../resources/js/wordpress-importer.js';

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source, "Source must be readable: {$path}");

        return $source;
    }

    // -- C-1 SSRF unification --------------------------------------------

    public function test_sync_download_consults_host_allow_list(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/downloadAndStoreImage[\s\S]*?isHostAllowed\(\s*\$image/s',
            $source,
            'downloadAndStoreImage() must consult isHostAllowed() before fetching — otherwise sync mode bypasses SSRF protection.'
        );
    }

    public function test_sync_download_records_host_block_as_failed_image(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/isHostAllowed[\s\S]*?failedImages\[\][\s\S]*?Host not allowed/s',
            $source,
            'SSRF-blocked URLs must be visible to the admin in the failed_images list.'
        );
    }

    public function test_fetch_helper_uses_timeout_and_max_bytes(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/fetchRemoteImageContents[\s\S]*?image_timeout_seconds[\s\S]*?image_max_bytes/s',
            $source,
            'Sync image fetch must honour configured timeout + max-bytes — otherwise a slow host stalls the worker.'
        );

        // file_get_contents max-length parameter (5th positional arg) must be set.
        $this->assertMatchesRegularExpression(
            '/file_get_contents\(\s*\$url\s*,\s*false\s*,\s*\$context\s*,\s*0\s*,\s*\$maxBytes\s*\)/',
            $source,
            'Sync image fetch must cap the read with file_get_contents()\'s $maxlen parameter.'
        );
    }

    public function test_sync_download_validates_mime_on_actual_bytes(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/mime_content_type\([^)]+\)/',
            $source,
            'MIME must be detected from the downloaded bytes, not the URL extension.'
        );

        $this->assertMatchesRegularExpression(
            '/isAllowedImageMime\(/',
            $source,
            'isAllowedImageMime() must gate the upload to RvMedia.'
        );
    }

    // -- C-2 XML hardening -----------------------------------------------

    public function test_libxml_external_entity_loader_is_disabled(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/libxml_set_external_entity_loader\s*\(\s*static\s+fn\s*\(\)\s*=>\s*null\s*\)/',
            $source,
            'libxml_set_external_entity_loader(static fn() => null) must run before any XML parsing to block external entity resolution.'
        );
    }

    public function test_simple_xml_element_uses_libxml_nonet(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/new SimpleXMLElement\([\s\S]*?LIBXML_NONET/',
            $source,
            'SimpleXMLElement constructor must include LIBXML_NONET to disable network resolution of external entities.'
        );
    }

    public function test_xml_reader_open_uses_libxml_nonet(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        // Every XMLReader::open call must pass LIBXML_NONET as the third arg.
        $this->assertMatchesRegularExpression(
            '/\$reader->open\(\s*\$this->xmlFilePath\s*,\s*null\s*,\s*LIBXML_NONET\s*\)/',
            $source,
            'XMLReader->open() must include LIBXML_NONET so the streaming parser also rejects network entities.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\$reader->open\(\s*\$this->xmlFilePath\s*\)/',
            $source,
            'No XMLReader->open() call may omit the LIBXML_NONET flag.'
        );
    }

    public function test_entity_declaration_rejection_helper_exists(): void
    {
        $source = $this->read(self::IMPORTER_PATH);

        $this->assertMatchesRegularExpression(
            '/protected function containsEntityDeclaration\(/',
            $source,
            'containsEntityDeclaration() pre-scan is required — a real WP export never declares entities, anything that does is suspect.'
        );

        $this->assertStringContainsString(
            'xml_entity_declaration_rejected',
            $source,
            'A rejection message must be surfaced to the admin (not a silent skip).'
        );
    }

    public function test_memory_limit_choices_no_longer_allow_unlimited(): void
    {
        $form = $this->read(self::FORM_PATH);
        $importer = $this->read(self::IMPORTER_PATH);

        $this->assertStringNotContainsString(
            "'-1'",
            $form,
            'memory_limit SelectField must not offer "-1" — unlimited PHP memory turns a hostile XML into an OOM weapon.'
        );

        // Importer-side: even if a request smuggles -1, the allow-list check must reject it.
        $this->assertMatchesRegularExpression(
            "/in_array\(\\\$memoryLimit\s*,\s*\['512M',\s*'1024M',\s*'2048M',\s*'4096M'\]/",
            $importer,
            'Importer must validate memory_limit against the same strict allow-list as the form.'
        );
    }

    // -- C-3 XSS fix in admin JS -----------------------------------------

    public function test_category_picker_does_not_interpolate_into_innerhtml(): void
    {
        $js = $this->read(self::JS_PATH);

        $this->assertStringNotContainsString(
            '${item.name}',
            $js,
            'item.name must not be interpolated into innerHTML — admin XSS risk via Category name.'
        );

        $this->assertStringNotContainsString(
            'value="${item.id}"',
            $js,
            'item.id must not be interpolated into an innerHTML template literal.'
        );

        // DOM construction methods must be used instead.
        $this->assertStringContainsString('createElement', $js);
        $this->assertStringContainsString('textContent', $js);
    }

    // -- H-2 Request validation ------------------------------------------

    public function test_request_validates_memory_limit_against_allow_list(): void
    {
        $source = $this->read(self::REQUEST_PATH);

        $this->assertMatchesRegularExpression(
            "/Rule::in\(\s*\['512M',\s*'1024M',\s*'2048M',\s*'4096M'\]\s*\)/",
            $source,
            'WordpressImporterRequest must validate memory_limit against a strict allow-list.'
        );
    }

    public function test_request_validates_image_mode_triplet(): void
    {
        $source = $this->read(self::REQUEST_PATH);

        $this->assertMatchesRegularExpression(
            "/Rule::in\(\s*\['sync',\s*'external',\s*'queue'\]\s*\)/",
            $source,
            'WordpressImporterRequest must validate image_mode against the supported triplet.'
        );
    }

    public function test_request_clamps_timeout_to_a_sane_range(): void
    {
        $source = $this->read(self::REQUEST_PATH);

        $this->assertMatchesRegularExpression(
            "/'timeout'[\s\S]*?'min:30'[\s\S]*?'max:86400'/",
            $source,
            'timeout must be clamped — values like 0 or 999999999 are abuse vectors.'
        );
    }

    // -- H-3 Plugin-presence guards in the job ---------------------------

    public function test_job_guards_against_inactive_blog_or_page_plugin(): void
    {
        $source = $this->read(self::JOB_PATH);

        $this->assertMatchesRegularExpression(
            "/is_plugin_active\(\s*'blog'\s*\)/",
            $source,
            'Download job must check is_plugin_active("blog") before touching Post records.'
        );

        $this->assertMatchesRegularExpression(
            '/hasTable\(\s*[\'"]posts[\'"]\s*\)/',
            $source,
            'Raw DB::table("posts") writes must be gated by a hasTable check.'
        );

        $this->assertMatchesRegularExpression(
            '/hasTable\(\s*[\'"]pages[\'"]\s*\)/',
            $source,
            'Raw DB::table("pages") writes must be gated by a hasTable check.'
        );

        $this->assertStringContainsString(
            'DB::getSchemaBuilder()',
            $source,
            'Job must obtain a SchemaBuilder so hasTable() can guard the raw DB writes.'
        );
    }

    // -- H-4 imageStatus aggregation -------------------------------------

    public function test_image_status_aggregates_counts_in_a_single_query(): void
    {
        $source = $this->read(self::CONTROLLER_PATH);

        $this->assertMatchesRegularExpression(
            '/selectRaw\(\s*[\'"]status,\s*COUNT\(\*\)\s+as\s+total[\'"]\s*\)[\s\S]*?groupBy\(\s*[\'"]status[\'"]\s*\)/',
            $source,
            'imageStatus() must aggregate counts via GROUP BY — never hydrate the entire rowset just to count statuses.'
        );

        // The previous implementation called ->get() then ->where()->count() five times in PHP.
        $this->assertStringNotContainsString(
            "->where('status', 'done')->count()",
            $source,
            'No in-PHP filter+count on a hydrated collection (was the audit H-4 finding).'
        );
    }

    public function test_image_status_caps_failed_url_list(): void
    {
        $source = $this->read(self::CONTROLLER_PATH);

        $this->assertMatchesRegularExpression(
            '/where\(\s*[\'"]status[\'"]\s*,\s*[\'"]failed[\'"]\s*\)[\s\S]*?limit\(\s*\d+\s*\)/',
            $source,
            'failed_urls payload must be capped — an unbounded list could be hundreds of MB on a busy worker.'
        );
    }
}
