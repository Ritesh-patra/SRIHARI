<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Minimal XLSX writer (Office Open XML) — no PhpSpreadsheet dependency.
 * Works without ZipArchive (pure PHP ZIP STORE). Falls back to CSV on failure.
 */
class SimpleXlsxExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<scalar|\DateTimeInterface|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $base = preg_replace('/\.(xlsx|csv)$/i', '', $filename) ?: 'export';
        $rowList = self::normalizeRows($rows);

        try {
            $binary = self::buildXlsx($headers, $rowList);

            return self::streamBinary(
                $base.'.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $binary
            );
        } catch (\Throwable $e) {
            report($e);

            return self::streamCsv($base.'.csv', $headers, $rowList);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<scalar|\DateTimeInterface|null>>  $rows
     */
    public static function downloadCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $base = preg_replace('/\.(xlsx|csv)$/i', '', $filename) ?: 'export';

        return self::streamCsv($base.'.csv', $headers, self::normalizeRows($rows));
    }

    /**
     * @param  iterable<int, list<scalar|\DateTimeInterface|null>>  $rows
     * @return list<list<string>>
     */
    private static function normalizeRows(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : array_values(iterator_to_array($row));
            $cells = [];
            foreach ($values as $value) {
                if ($value instanceof \DateTimeInterface) {
                    $cells[] = $value->format('d M Y, H:i');
                } elseif (is_bool($value)) {
                    $cells[] = $value ? '1' : '0';
                } elseif ($value === null) {
                    $cells[] = '';
                } else {
                    // Strip control chars that break XML / CSV streams on some hosts.
                    $cells[] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value) ?? '';
                }
            }
            $out[] = $cells;
        }

        return $out;
    }

    private static function streamBinary(string $filename, string $contentType, string $binary): StreamedResponse
    {
        return response()->streamDownload(function () use ($binary) {
            self::clearOutputBuffers();
            echo $binary;
        }, $filename, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            self::clearOutputBuffers();
            $out = fopen('php://output', 'w');
            if ($out === false) {
                echo "Download failed: unable to open output stream.\n";

                return;
            }
            // UTF-8 BOM so Excel opens CSV correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function buildXlsx(array $headers, array $rows): string
    {
        $files = [
            '[Content_Types].xml' => self::contentTypes(),
            '_rels/.rels' => self::rels(),
            'xl/workbook.xml' => self::workbook(),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(),
            'xl/worksheets/sheet1.xml' => self::sheetXml($headers, $rows),
        ];

        // Prefer ZipArchive when present; always have a pure-PHP fallback.
        if (class_exists(\ZipArchive::class)) {
            try {
                return self::buildViaZipArchive($files);
            } catch (\Throwable) {
                // Fall through — temp dir / zip extension quirks on shared hosting.
            }
        }

        return self::buildZipStore($files);
    }

    /** @param  array<string, string>  $files */
    private static function buildViaZipArchive(array $files): string
    {
        $dirCandidates = [
            function_exists('storage_path') ? storage_path('app') : null,
            function_exists('storage_path') ? storage_path('framework/cache') : null,
            sys_get_temp_dir(),
        ];

        $zipPath = null;
        foreach ($dirCandidates as $dir) {
            if (! is_string($dir) || $dir === '' || ! is_dir($dir) || ! is_writable($dir)) {
                continue;
            }
            $zipPath = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'xlsx_'.uniqid('', true).'.xlsx';
            break;
        }

        if ($zipPath === null) {
            throw new \RuntimeException('No writable temp directory for Excel export.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create Excel archive.');
        }

        foreach ($files as $name => $content) {
            if ($zip->addFromString($name, $content) === false) {
                $zip->close();
                @unlink($zipPath);
                throw new \RuntimeException('Unable to write Excel archive entry.');
            }
        }
        $zip->close();

        $binary = @file_get_contents($zipPath);
        @unlink($zipPath);

        if ($binary === false || $binary === '') {
            throw new \RuntimeException('Unable to read Excel archive.');
        }

        return $binary;
    }

    /**
     * Uncompressed ZIP (STORE) — no ZipArchive / zlib required.
     *
     * @param  array<string, string>  $files
     */
    private static function buildZipStore(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($files as $name => $content) {
            $name = (string) $name;
            $content = (string) $content;
            $size = strlen($content);
            $crc = self::crc32u($content);
            $nameLen = strlen($name);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $nameLen,
                0
            );

            $local .= $localHeader.$name.$content;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $nameLen,
                0,
                0,
                0,
                0,
                0,
                $offset
            ).$name;

            $offset = strlen($local);
            $count++;
        }

        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            strlen($central),
            strlen($local),
            0
        );

        return $local.$central.$end;
    }

    private static function crc32u(string $data): int
    {
        return (int) hexdec(hash('crc32b', $data));
    }

    private static function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private static function rels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Report" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private static function workbookRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function sheetXml(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $r = 1;
        $xml .= self::rowXml($r, $headers);
        $r++;

        foreach ($rows as $row) {
            $xml .= self::rowXml($r, $row);
            $r++;
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /** @param  list<string>  $cells */
    private static function rowXml(int $rowNum, array $cells): string
    {
        $xml = '<row r="'.$rowNum.'">';
        foreach ($cells as $i => $value) {
            $col = self::colName($i + 1);
            $ref = $col.$rowNum;
            $text = self::escape($value);
            // Excel rejects control chars / empty <t> with xml:space issues; keep simple.
            $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
        }

        return $xml.'</row>';
    }

    private static function colName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
