<?php

namespace App\Support;

/**
 * Headers + data-row count for a Report Analysis upload.
 *
 * Everything streams through StreamingSheetReader, so a 300 MB CSV or XLSX is
 * inspected with bounded memory (the old simplexml_load_string XLSX path used to
 * load sharedStrings.xml and sheet1.xml fully into RAM and OOM'd on big files).
 * XLS (BIFF) still cannot be parsed without a library — the file is kept and the
 * user is told to re-save it.
 */
class ReportAnalysisFileParser
{
    /**
     * @return array{headers: ?array<int, string>, row_count: ?int, parse_note: ?string}
     */
    public static function inspect(string $absolutePath, string $extension): array
    {
        $ext = strtolower($extension);

        if (StreamingSheetReader::isLegacyXls($ext)) {
            return [
                'headers' => null,
                'row_count' => null,
                'parse_note' => 'Stored — '.StreamingSheetReader::LEGACY_XLS_MESSAGE,
            ];
        }

        if (! StreamingSheetReader::supports($ext)) {
            return [
                'headers' => null,
                'row_count' => null,
                'parse_note' => 'Stored — parsing deferred',
            ];
        }

        try {
            $headers = null;
            $rows = 0;

            foreach (StreamingSheetReader::readRows($absolutePath, $ext) as $row) {
                if ($headers === null) {
                    $headers = array_map(static fn ($c) => trim((string) $c), $row);

                    continue;
                }

                if (self::isBlankRow($row)) {
                    continue;
                }

                $rows++;
            }

            if ($headers === null) {
                return [
                    'headers' => [],
                    'row_count' => 0,
                    'parse_note' => null,
                ];
            }

            return [
                'headers' => array_values($headers),
                'row_count' => $rows,
                'parse_note' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'headers' => null,
                'row_count' => null,
                'parse_note' => 'Stored — could not parse: '.$e->getMessage(),
            ];
        }
    }

    /** @param  array<int, string>  $row */
    private static function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
