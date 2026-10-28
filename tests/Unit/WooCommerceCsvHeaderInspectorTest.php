<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\WordpressImporter\Support\WooCommerceCsvHeaderInspector;
use PHPUnit\Framework\TestCase;

/**
 * Header checks the WooCommerce product importer runs before validating rows,
 * so a wrong file reports its missing columns once instead of per row.
 */
class WooCommerceCsvHeaderInspectorTest extends TestCase
{
    private const REQUIRED = ['Name', 'Published', 'Is featured?', 'In stock?'];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../src/Support/WooCommerceCsvHeaderInspector.php';
    }

    public function test_woocommerce_export_headers_pass(): void
    {
        $headers = ['ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog', 'In stock?', 'Stock'];

        $this->assertSame([], WooCommerceCsvHeaderInspector::missing($headers, self::REQUIRED));
    }

    public function test_custom_spreadsheet_reports_every_missing_column(): void
    {
        $headers = ['ID', 'Name', 'Description', 'Slug', 'URL'];

        $this->assertSame(
            ['Published', 'Is featured?', 'In stock?'],
            WooCommerceCsvHeaderInspector::missing($headers, self::REQUIRED)
        );
    }

    public function test_translated_woocommerce_export_is_rejected(): void
    {
        $headers = ['ID', 'Loại', 'Mã sản phẩm', 'Tên', 'Đã xuất bản', 'Nổi bật?', 'Còn hàng?'];

        $this->assertSame(self::REQUIRED, WooCommerceCsvHeaderInspector::missing($headers, self::REQUIRED));
    }

    public function test_bom_and_whitespace_around_headers_are_ignored(): void
    {
        $headers = ["\u{FEFF}Name", ' Published ', "Is featured?\t", 'In stock?'];

        $this->assertSame([], WooCommerceCsvHeaderInspector::missing($headers, self::REQUIRED));
    }

    public function test_normalize_row_keys_keeps_values(): void
    {
        $row = ["\u{FEFF}ID" => '12', ' Name ' => ' Hoodie ', 'Attribute 1 name' => 'Color', 0 => 'x'];

        $this->assertSame(
            ['ID' => '12', 'Name' => ' Hoodie ', 'Attribute 1 name' => 'Color', 0 => 'x'],
            WooCommerceCsvHeaderInspector::normalizeRowKeys($row)
        );
    }
}
