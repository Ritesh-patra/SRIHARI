<?php

namespace App\Support;

/**
 * Bounded-memory row reader for CSV / TXT / XLSX — no PhpSpreadsheet, no composer deps.
 *
 * CSV / TXT : fopen + fgetcsv with delimiter sniffing.
 * XLSX      : ZipArchive to locate parts, XMLReader streaming over the first worksheet.
 *             Shared strings are streamed into a SharedStringStore which spools to two
 *             temp files (data + fixed-width offset index) once it outgrows its memory
 *             budget, so sharedStrings.xml is never held in RAM as one big array.
 *             The worksheet itself is read through the `zip://` stream wrapper so the
 *             (often multi-GB uncompressed) sheet XML is never extracted to disk; if the
 *             wrapper is unavailable the entry is spooled to a temp file instead.
 * XLS       : legacy BIFF — not parseable without a library; callers get a clear message.
 *
 * Usage:
 *   foreach (StreamingSheetReader::readRows($path) as $row) { ... }
 *   $headers = StreamingSheetReader::readHeaders($path);
 */
class StreamingSheetReader
{
    public const LEGACY_XLS_MESSAGE = 'Legacy .xls files cannot be parsed on this server — please re-save the file as .xlsx or .csv and upload again.';

    /** Shared strings kept in RAM until this many bytes accumulate. */
    private const SHARED_STRING_MEMORY_BUDGET = 4194304;

    /** styles.xml above this size is skipped (date detection is best-effort). */
    private const MAX_STYLES_BYTES = 20971520;

    private string $extension;

    /** @var list<string>|null */
    private ?array $headers = null;

    public function __construct(private readonly string $absolutePath, ?string $extension = null)
    {
        $this->extension = strtolower((string) ($extension ?: pathinfo($absolutePath, PATHINFO_EXTENSION)));
    }

    public static function make(string $absolutePath, ?string $extension = null): self
    {
        return new self($absolutePath, $extension);
    }

    /** @return iterable<int, list<string>> */
    public static function readRows(string $absolutePath, ?string $extension = null): iterable
    {
        return (new self($absolutePath, $extension))->rows();
    }

    /** @return list<string> */
    public static function readHeaders(string $absolutePath, ?string $extension = null): array
    {
        return (new self($absolutePath, $extension))->headers();
    }

    public static function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['csv', 'txt', 'xlsx'], true);
    }

    public static function isLegacyXls(string $extension): bool
    {
        return strtolower($extension) === 'xls';
    }

    public function extension(): string
    {
        return $this->extension;
    }

    /**
     * Rows in sheet order, each a zero-indexed list of trimmed cell strings.
     *
     * @return \Generator<int, list<string>>
     */
    public function rows(?string $absolutePath = null): \Generator
    {
        $path = $absolutePath ?? $this->absolutePath;

        if (! is_file($path)) {
            throw new \RuntimeException('Uploaded file is missing from storage.');
        }

        if (self::isLegacyXls($this->extension)) {
            throw new \RuntimeException(self::LEGACY_XLS_MESSAGE);
        }

        if ($this->extension === 'xlsx') {
            yield from $this->xlsxRows($path);

            return;
        }

        yield from $this->csvRows($path);
    }

    /** @return list<string> */
    public function headers(): array
    {
        if ($this->headers !== null) {
            return $this->headers;
        }

        foreach ($this->rows() as $row) {
            $this->headers = array_values(array_map(static fn ($c) => trim((string) $c), $row));

            return $this->headers;
        }

        $this->headers = [];

        return $this->headers;
    }

    // -------------------------------------------------------------------------
    // CSV
    // -------------------------------------------------------------------------

    /** @return \Generator<int, list<string>> */
    private function csvRows(string $path): \Generator
    {
        $delimiter = self::sniffDelimiter($path);

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open file for reading.');
        }

        try {
            $first = true;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($row === [null]) {
                    continue; // blank line
                }
                if ($first) {
                    $first = false;
                    if (isset($row[0])) {
                        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
                    }
                }

                yield array_values(array_map(static fn ($c) => trim((string) $c), $row));
            }
        } finally {
            fclose($handle);
        }
    }

    private static function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ',';
        }

        try {
            $line = fgets($handle, 65536);
        } finally {
            fclose($handle);
        }

        if (! is_string($line) || $line === '') {
            return ',';
        }

        $line = preg_replace('/"[^"]*"/', '', $line) ?? $line;

        $best = ',';
        $bestCount = substr_count($line, ',');
        foreach ([';' => substr_count($line, ';'), "\t" => substr_count($line, "\t"), '|' => substr_count($line, '|')] as $candidate => $count) {
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    // -------------------------------------------------------------------------
    // XLSX
    // -------------------------------------------------------------------------

    /** @return \Generator<int, list<string>> */
    private function xlsxRows(string $path): \Generator
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The zip PHP extension is required to read .xlsx files.');
        }
        if (! class_exists(\XMLReader::class)) {
            throw new \RuntimeException('The xmlreader PHP extension is required to read .xlsx files.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open the .xlsx archive — the file may be corrupt.');
        }

        $shared = new SharedStringStore(self::SHARED_STRING_MEMORY_BUDGET);
        $tempFiles = [];
        $zipOpen = true;

        try {
            $sheetEntry = $this->resolveFirstSheetEntry($zip);
            if ($sheetEntry === null) {
                throw new \RuntimeException('No worksheet found inside the .xlsx file.');
            }

            $dateStyles = $this->readDateStyles($zip);

            $sharedSource = $this->entrySource($zip, 'xl/sharedStrings.xml', $tempFiles);
            if ($sharedSource !== null) {
                $this->loadSharedStrings($sharedSource, $shared);
            }
            $shared->finalize();

            $sheetSource = $this->entrySource($zip, $sheetEntry, $tempFiles);
            if ($sheetSource === null) {
                throw new \RuntimeException('Could not read the worksheet inside the .xlsx file.');
            }

            $zip->close();
            $zipOpen = false;

            yield from $this->streamWorksheet($sheetSource, $shared, $dateStyles);
        } finally {
            if ($zipOpen) {
                @$zip->close();
            }
            $shared->close();
            foreach ($tempFiles as $temp) {
                if (is_file($temp)) {
                    @unlink($temp);
                }
            }
        }
    }

    /** @return \Generator<int, list<string>> */
    private function streamWorksheet(string $source, SharedStringStore $shared, array $dateStyles): \Generator
    {
        $reader = new \XMLReader;
        if (@$reader->open($source) === false) {
            throw new \RuntimeException('Could not stream the worksheet XML.');
        }

        try {
            while (@$reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                if ($reader->isEmptyElement) {
                    yield [];

                    continue;
                }

                yield $this->readRowCells($reader, $shared, $dateStyles);
            }
        } finally {
            $reader->close();
        }
    }

    /** @return list<string> */
    private function readRowCells(\XMLReader $reader, SharedStringStore $shared, array $dateStyles): array
    {
        $cells = [];
        $maxIndex = -1;
        $nextIndex = 0;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'row') {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $ref = $reader->getAttribute('r');
            $type = (string) $reader->getAttribute('t');
            $styleAttr = $reader->getAttribute('s');
            $styleIndex = $styleAttr === null ? null : (int) $styleAttr;

            $column = $ref !== null ? self::columnIndexFromRef($ref) : $nextIndex;
            $nextIndex = $column + 1;

            if ($reader->isEmptyElement) {
                $cells[$column] = '';
                $maxIndex = max($maxIndex, $column);

                continue;
            }

            [$valueText, $inlineText] = $this->readCellText($reader);

            $cells[$column] = $this->formatCellValue($type, $valueText, $inlineText, $styleIndex, $shared, $dateStyles);
            $maxIndex = max($maxIndex, $column);
        }

        if ($maxIndex < 0) {
            return [];
        }

        $out = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $out[] = $cells[$i] ?? '';
        }

        return $out;
    }

    /**
     * Walk the children of the current <c> element, collecting <v> and inline <is><t> text.
     *
     * @return array{0: string, 1: ?string}
     */
    private function readCellText(\XMLReader $reader): array
    {
        $stack = [];
        $valueText = '';
        $inlineText = null;
        $insideInline = false;

        while ($reader->read()) {
            $nodeType = $reader->nodeType;

            if ($nodeType === \XMLReader::ELEMENT) {
                $name = $reader->localName;
                if ($name === 'is') {
                    $insideInline = true;
                    $inlineText ??= '';
                }
                if (! $reader->isEmptyElement) {
                    $stack[] = $name;
                }

                continue;
            }

            if ($nodeType === \XMLReader::END_ELEMENT) {
                $name = $reader->localName;
                if ($name === 'c' && $stack === []) {
                    break;
                }
                array_pop($stack);
                if ($name === 'is') {
                    $insideInline = false;
                }

                continue;
            }

            if ($nodeType === \XMLReader::TEXT || $nodeType === \XMLReader::CDATA || $nodeType === \XMLReader::SIGNIFICANT_WHITESPACE) {
                $top = $stack === [] ? '' : $stack[count($stack) - 1];
                if ($top === 'v') {
                    $valueText .= $reader->value;
                } elseif ($insideInline && $top === 't') {
                    $inlineText = ($inlineText ?? '').$reader->value;
                }
            }
        }

        return [$valueText, $inlineText];
    }

    private function formatCellValue(
        string $type,
        string $valueText,
        ?string $inlineText,
        ?int $styleIndex,
        SharedStringStore $shared,
        array $dateStyles,
    ): string {
        return match ($type) {
            's' => trim($shared->get((int) $valueText)),
            'inlineStr' => trim((string) $inlineText),
            'str' => trim($valueText),
            'b' => $valueText === '1' ? 'TRUE' : 'FALSE',
            'e' => trim($valueText),
            default => $this->formatNumericValue($valueText, $styleIndex, $dateStyles),
        };
    }

    private function formatNumericValue(string $valueText, ?int $styleIndex, array $dateStyles): string
    {
        $valueText = trim($valueText);
        if ($valueText === '') {
            return '';
        }

        if ($styleIndex !== null && ! empty($dateStyles[$styleIndex]) && is_numeric($valueText)) {
            return self::excelSerialToDate((float) $valueText);
        }

        return $valueText;
    }

    private function loadSharedStrings(string $source, SharedStringStore $shared): void
    {
        $reader = new \XMLReader;
        if (@$reader->open($source) === false) {
            return;
        }

        try {
            if (! @$reader->read()) {
                return;
            }

            while ($reader->nodeType !== \XMLReader::NONE) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                    $shared->push($reader->isEmptyElement ? '' : self::extractSharedText($reader->readOuterXml()));

                    // readOuterXml() leaves the cursor put, so jump straight to the next
                    // sibling instead of letting read() walk the subtree we just consumed.
                    if (! @$reader->next()) {
                        break;
                    }

                    continue;
                }

                if (! @$reader->read()) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /** Concatenate every <t> run inside one <si> fragment. */
    private static function extractSharedText(string $xml): string
    {
        if ($xml === '') {
            return '';
        }

        // Phonetic guides are display-only noise in Japanese workbooks.
        $xml = preg_replace('/<rPh\b.*?<\/rPh>/s', '', $xml) ?? $xml;

        $matches = [];
        if (! preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $xml, $matches) || empty($matches[1])) {
            return '';
        }

        return html_entity_decode(implode('', $matches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Map cellXfs index => true when the format renders as a date/time.
     *
     * @return array<int, bool>
     */
    private function readDateStyles(\ZipArchive $zip): array
    {
        $index = $zip->locateName('xl/styles.xml');
        if ($index === false) {
            return [];
        }

        $stat = $zip->statIndex($index);
        if (is_array($stat) && ($stat['size'] ?? 0) > self::MAX_STYLES_BYTES) {
            return [];
        }

        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false || $xml === '') {
            return [];
        }

        $builtinDateIds = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57];
        $customDateIds = [];
        $styles = [];

        $reader = new \XMLReader;
        if (@$reader->XML($xml) === false) {
            return [];
        }

        try {
            $inCellXfs = false;
            $xfIndex = 0;

            while (@$reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'numFmt') {
                    $id = (int) $reader->getAttribute('numFmtId');
                    $code = (string) $reader->getAttribute('formatCode');
                    if (self::formatCodeLooksLikeDate($code)) {
                        $customDateIds[$id] = true;
                    }

                    continue;
                }

                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'cellXfs') {
                    $inCellXfs = true;

                    continue;
                }
                if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'cellXfs') {
                    $inCellXfs = false;

                    continue;
                }

                if ($inCellXfs && $reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'xf') {
                    $numFmtId = (int) $reader->getAttribute('numFmtId');
                    $styles[$xfIndex] = in_array($numFmtId, $builtinDateIds, true) || isset($customDateIds[$numFmtId]);
                    $xfIndex++;
                }
            }
        } finally {
            $reader->close();
        }

        return $styles;
    }

    private static function formatCodeLooksLikeDate(string $code): bool
    {
        if ($code === '' || stripos($code, 'general') !== false) {
            return false;
        }

        // Drop quoted literals, escaped chars, colour/condition blocks.
        $stripped = preg_replace('/"[^"]*"/', '', $code) ?? $code;
        $stripped = preg_replace('/\[[^\]]*\]/', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\\\\./', '', $stripped) ?? $stripped;

        return (bool) preg_match('/[dmyhs]/i', $stripped);
    }

    /** 1900-based Excel serial to an ISO-ish string. */
    private static function excelSerialToDate(float $serial): string
    {
        if ($serial <= 0) {
            return rtrim(rtrim(number_format($serial, 6, '.', ''), '0'), '.');
        }

        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);

        if ($days === 0) {
            return gmdate('H:i:s', $seconds);
        }

        // Excel wrongly treats 1900 as a leap year; serials up to 59 are one day off.
        if ($days <= 59) {
            $days++;
        }

        $timestamp = ($days - 25569) * 86400 + $seconds;

        return $seconds === 0 ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d H:i:s', $timestamp);
    }

    /** Resolve the first worksheet part from the workbook relationships. */
    private function resolveFirstSheetEntry(\ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (is_string($workbook) && is_string($rels)
            && preg_match('/<sheet\b[^>]*r:id="([^"]+)"/', $workbook, $sheetMatch) === 1
            && preg_match('/<Relationship\b[^>]*Id="'.preg_quote($sheetMatch[1], '/').'"[^>]*Target="([^"]+)"/', $rels, $relMatch) === 1
        ) {
            $target = ltrim($relMatch[1], '/');
            $candidate = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            if ($zip->locateName($candidate) !== false) {
                return $candidate;
            }
        }

        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $fallback) {
            if ($zip->locateName($fallback) !== false) {
                return $fallback;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * A URI XMLReader can stream — the zip:// wrapper when available, otherwise a temp copy.
     *
     * @param  list<string>  $tempFiles
     */
    private function entrySource(\ZipArchive $zip, string $entry, array &$tempFiles): ?string
    {
        if ($zip->locateName($entry) === false) {
            return null;
        }

        if (in_array('zip', stream_get_wrappers(), true)) {
            $uri = 'zip://'.$this->absolutePath.'#'.$entry;
            $probe = @fopen($uri, 'rb');
            if ($probe !== false) {
                fclose($probe);

                return $uri;
            }
        }

        $stream = $zip->getStream($entry);
        if (! is_resource($stream)) {
            return null;
        }

        $temp = self::tempPath('seas_xlsx_');
        $out = fopen($temp, 'wb');
        if ($out === false) {
            fclose($stream);

            return null;
        }

        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $tempFiles[] = $temp;

        return $temp;
    }

    private static function tempPath(string $prefix): string
    {
        $dir = function_exists('storage_path') ? storage_path('app/private/tmp') : sys_get_temp_dir();
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }

        return rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$prefix.uniqid('', true).'.xml';
    }

    private static function columnIndexFromRef(string $ref): int
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $ref) ?? '';
        if ($letters === '') {
            return 0;
        }

        $letters = strtoupper($letters);
        $n = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $n - 1);
    }
}
