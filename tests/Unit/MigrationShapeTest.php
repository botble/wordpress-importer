<?php

namespace Botble\WordpressImporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Validates the wordpress_import_images migration source:
 *  - Idempotent (Schema::hasTable guard) — plugins activate/deactivate
 *    repeatedly during demo seeding.
 *  - Carries the (import_id, url_hash) unique index that prevents the race
 *    in DownloadWordPressImage::handle() identified in code review.
 *  - down() drops cleanly.
 *
 * @see platform/plugins/wordpress-importer/database/migrations/2026_05_12_000000_create_wordpress_import_images_table.php
 * @see botble-plugin-migrator skill: idempotent migrations
 */
class MigrationShapeTest extends TestCase
{
    private const MIGRATION_PATH = __DIR__ . '/../../database/migrations/2026_05_12_000000_create_wordpress_import_images_table.php';

    private function migrationSource(): string
    {
        $source = file_get_contents(self::MIGRATION_PATH);
        $this->assertNotFalse($source, 'Migration file must be readable.');

        return $source;
    }

    public function test_migration_file_exists(): void
    {
        $this->assertFileExists(self::MIGRATION_PATH);
    }

    public function test_migration_returns_anonymous_class_extending_migration(): void
    {
        $source = $this->migrationSource();

        $this->assertMatchesRegularExpression(
            '/return\s+new\s+class\s*\(\)\s+extends\s+Migration/',
            $source,
            'Migrations must use the anonymous class pattern (Laravel 9+ standard).'
        );
    }

    public function test_up_method_guards_with_has_table(): void
    {
        $source = $this->migrationSource();

        $this->assertMatchesRegularExpression(
            '/public\s+function\s+up\(\)\s*:\s*void\s*\{[\s\S]*?Schema::hasTable\(\s*[\'"]wordpress_import_images[\'"]/',
            $source,
            'up() must early-return when the table already exists (idempotency for plugin activate/deactivate cycles).'
        );
    }

    public function test_down_uses_drop_if_exists(): void
    {
        $source = $this->migrationSource();

        $this->assertStringContainsString(
            "Schema::dropIfExists('wordpress_import_images')",
            $source,
            'down() must use dropIfExists to be safe against partial state.'
        );
    }

    public function test_url_hash_column_is_char_40(): void
    {
        $source = $this->migrationSource();

        $this->assertMatchesRegularExpression(
            '/\$table->char\(\s*[\'"]url_hash[\'"]\s*,\s*40\s*\)/',
            $source,
            'url_hash must be CHAR(40) — SHA1 hex — to bound the unique index size.'
        );
    }

    public function test_composite_unique_on_import_id_and_url_hash(): void
    {
        $source = $this->migrationSource();

        $this->assertMatchesRegularExpression(
            '/\$table->unique\(\s*\[\s*[\'"]import_id[\'"]\s*,\s*[\'"]url_hash[\'"]\s*\]/',
            $source,
            'Composite unique on (import_id, url_hash) is required — without it concurrent jobs race-create duplicate rows.'
        );
    }

    public function test_status_column_is_a_bounded_string_not_enum(): void
    {
        $source = $this->migrationSource();

        // Botble convention: avoid MySQL ENUM (hard to ALTER); use varchar+app constraint.
        $this->assertDoesNotMatchRegularExpression(
            '/\$table->enum\(\s*[\'"]status[\'"]/',
            $source,
            'Avoid ENUM for status — use varchar/string so future statuses can be added without ALTER TABLE.'
        );

        $this->assertMatchesRegularExpression(
            '/\$table->string\(\s*[\'"]status[\'"]\s*,\s*\d+\s*\)/',
            $source,
            'status must be a bounded string column.'
        );
    }

    public function test_indexes_cover_import_lookup_path(): void
    {
        $source = $this->migrationSource();

        $this->assertMatchesRegularExpression(
            '/\$table->index\(\s*\[\s*[\'"]import_id[\'"]\s*,\s*[\'"]status[\'"]\s*\]/',
            $source,
            'A composite index on (import_id, status) is required for the retry-failed and status endpoints.'
        );
    }
}
