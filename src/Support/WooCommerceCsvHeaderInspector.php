<?php

namespace Botble\WordpressImporter\Support;

/**
 * Checks a CSV header row against the column names of a WooCommerce product
 * export, so a wrong file fails with one clear message instead of a
 * "field is required" error for every row.
 */
class WooCommerceCsvHeaderInspector
{
    /**
     * Strips the UTF-8 BOM and surrounding whitespace that spreadsheet apps
     * add to header cells when re-saving a CSV.
     */
    public static function normalize(string $header): string
    {
        return trim(str_replace("\u{FEFF}", '', $header));
    }

    public static function normalizeRowKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[is_string($key) ? static::normalize($key) : $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $headers  Header cells of the uploaded file.
     * @param  array<int, string>  $requiredHeadings  Headings the importer cannot work without.
     * @return array<int, string> Required headings absent from the file, in the given order.
     */
    public static function missing(array $headers, array $requiredHeadings): array
    {
        $present = array_map(fn ($header) => static::normalize((string) $header), $headers);

        return array_values(array_diff($requiredHeadings, $present));
    }
}
