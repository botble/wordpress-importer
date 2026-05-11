<?php

namespace Botble\WordpressImporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 01 regression locks for the wordpress-importer plugin.
 *
 * Each fix corresponds to a real bug identified in the PerakKini customer case
 * (11 May 2026, 695 / 1,807 posts missing after import). These tests verify the
 * fix patterns remain in the source so an accidental revert is caught at CI time.
 *
 * @see plans/260511-1522-wordpress-importer-improvements/phase-01-critical-fixes.md
 */
class Phase01RegressionTest extends TestCase
{
    private const IMPORTER_PATH = __DIR__ . '/../../src/WordpressImporter.php';
    private const PRODUCT_IMPORTER_PATH = __DIR__ . '/../../src/Importers/ProductImporter.php';
    private const FORM_PATH = __DIR__ . '/../../src/Forms/WordpressImporterForm.php';

    private function importerSource(): string
    {
        $source = file_get_contents(self::IMPORTER_PATH);
        $this->assertNotFalse($source, 'WordpressImporter.php must be readable.');

        return $source;
    }

    public function test_memory_limit_is_set_via_ini_set(): void
    {
        $source = $this->importerSource();

        $this->assertMatchesRegularExpression(
            '/@?ini_set\(\s*[\'"]memory_limit[\'"]/s',
            $source,
            'verifyRequest() must call ini_set("memory_limit", ...) — large imports OOM silently without it.'
        );
    }

    public function test_memory_limit_field_added_to_form(): void
    {
        $source = file_get_contents(self::FORM_PATH);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            "'memory_limit'",
            $source,
            'WordpressImporterForm must expose a memory_limit form field.'
        );
    }

    public function test_user_default_password_hardcoded_property_is_removed(): void
    {
        $source = $this->importerSource();

        $this->assertDoesNotMatchRegularExpression(
            '/\$userDefaultPassword\s*=\s*[\'"]password[\'"]/',
            $source,
            'The literal $userDefaultPassword = "password" must NOT exist — it created weak-default accounts.'
        );
    }

    public function test_hash_make_is_not_called_before_user_create(): void
    {
        $source = $this->importerSource();

        // The User model has `'password' => 'hashed'` cast. Calling Hash::make()
        // before User::create() double-hashes and locks accounts out.
        $this->assertDoesNotMatchRegularExpression(
            '/Hash::make\(\$this->userDefaultPassword\)/',
            $source,
            'Hash::make(...) must NOT wrap the password — the User model already has a "hashed" cast (double-hash regression).'
        );
    }

    public function test_passwords_use_str_random_with_safe_length(): void
    {
        $source = $this->importerSource();

        $this->assertMatchesRegularExpression(
            '/Str::random\(\s*(?:1[6-9]|[2-9]\d|1\d\d)\s*\)/',
            $source,
            'Generated passwords must use Str::random() with length >= 16 for adequate entropy.'
        );
    }

    public function test_no_hardcoded_tmp_path_in_importer(): void
    {
        $source = $this->importerSource();

        // Look for the literal '/tmp' as a path (in a string), not as part of a comment.
        $this->assertDoesNotMatchRegularExpression(
            '/[\'"]\/tmp[\'"]/',
            $source,
            'WordpressImporter must NOT use the hardcoded "/tmp" path — restricted hosts deny it.'
        );

        $this->assertStringContainsString(
            "storage_path('app/tmp/wordpress-import')",
            $source,
            'Temp downloads must live under storage_path("app/tmp/wordpress-import").'
        );
    }

    public function test_no_hardcoded_tmp_path_in_product_importer(): void
    {
        $source = file_get_contents(self::PRODUCT_IMPORTER_PATH);
        $this->assertNotFalse($source);

        $this->assertDoesNotMatchRegularExpression(
            '/\$path\s*=\s*[\'"]\/tmp[\'"]/',
            $source,
            'ProductImporter must NOT assign $path = "/tmp" — restricted hosts deny it.'
        );

        $this->assertStringContainsString(
            "storage_path('app/tmp/wordpress-import')",
            $source,
            'ProductImporter temp downloads must live under storage_path("app/tmp/wordpress-import").'
        );
    }

    public function test_image_url_cache_property_exists(): void
    {
        $source = $this->importerSource();

        $this->assertMatchesRegularExpression(
            '/protected\s+array\s+\$imageUrlCache\b/',
            $source,
            '$imageUrlCache property is required — same image URL must not be fetched twice.'
        );
    }

    public function test_get_image_consults_cache_before_downloading(): void
    {
        $source = $this->importerSource();

        $this->assertMatchesRegularExpression(
            '/getImage\([^)]*\).*?array_key_exists\(\$image,\s*\$this->imageUrlCache\)/s',
            $source,
            'getImage() must early-return the cached URL when one exists.'
        );
    }

    public function test_failed_images_are_collected(): void
    {
        $source = $this->importerSource();

        $this->assertMatchesRegularExpression(
            '/protected\s+array\s+\$failedImages\b/',
            $source,
            '$failedImages collector is required — silent download failures previously left users guessing.'
        );

        $this->assertStringContainsString(
            "'failed_images' => \$this->failedImages",
            $source,
            'Import result must include failed_images so the controller can surface them.'
        );
    }
}
