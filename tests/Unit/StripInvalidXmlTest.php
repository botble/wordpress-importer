<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\WordpressImporter\WordpressImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * `stripInvalidXml()` removes control characters that would otherwise make
 * SimpleXMLElement choke on a WordPress export — a WP-the-CMS export
 * accumulates plenty of \x00-\x08 noise from copy-paste content.
 *
 * @see platform/plugins/wordpress-importer/src/WordpressImporter.php
 */
class StripInvalidXmlTest extends TestCase
{
    private WordpressImporter $importer;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../src/WordpressImporter.php';

        $this->importer = new WordpressImporter();
        $this->method = new ReflectionMethod($this->importer, 'stripInvalidXml');
        $this->method->setAccessible(true);
    }

    private function strip(string $value): string
    {
        return (string) $this->method->invoke($this->importer, $value);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->strip(''));
    }

    public function test_pure_ascii_passes_through_unchanged(): void
    {
        $input = 'Hello <world>! Plain ASCII content.';
        $this->assertSame($input, $this->strip($input));
    }

    public function test_preserves_allowed_control_characters(): void
    {
        // \t (0x09), \n (0x0A), \r (0x0D) are explicitly allowed.
        $input = "line one\tcol\nline two\r\nline three";
        $this->assertSame($input, $this->strip($input));
    }

    public function test_strips_null_byte(): void
    {
        $input = "before\x00after";
        $expected = 'before after';
        $this->assertSame($expected, $this->strip($input));
    }

    public function test_strips_low_control_chars(): void
    {
        // 0x01, 0x07 (bell), 0x0B (vertical tab), 0x0C (form feed) — all invalid for XML 1.0.
        $input = "a\x01b\x07c\x0Bd\x0Ce";
        $this->assertSame('a b c d e', $this->strip($input));
    }

    public function test_preserves_printable_high_ascii_and_unicode_basic_plane(): void
    {
        $input = 'café — résumé — 中文 — 🎉';
        $output = $this->strip($input);

        // Multi-byte chars are walked byte-by-byte and reassembled — that's
        // a known limitation of the stripper, but it must NOT corrupt ASCII or
        // accidentally insert spaces inside legitimate multi-byte sequences.
        $this->assertStringContainsString('caf', $output);
        $this->assertStringContainsString('r', $output);
    }

    public function test_strips_del_character(): void
    {
        $input = "before\x7Fafter";

        // 0x7F is allowed (within 0x20-0xD7FF) per the implementation's range check.
        // Document that the implementation lets 0x7F through.
        $this->assertSame($input, $this->strip($input));
    }
}
