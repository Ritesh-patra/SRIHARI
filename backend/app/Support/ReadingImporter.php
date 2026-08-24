<?php

namespace App\Support;

use App\Models\Consumer;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\ReadingUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Streams a Feeder / DTR / Consumer consumption file into its readings table.
 *
 * Rows are buffered into fixed-size batches; master ids (feeders.code, dtrs.code,
 * consumers.ivrs|msn|account_no) are resolved with one chunked whereIn per batch
 * rather than a query per row, then the batch goes in via a single insert().
 */
class ReadingImporter
{
    private int $batchSize;

    private int $rowsTotal = 0;

    private int $rowsImported = 0;

    private int $rowsFailed = 0;

    public function __construct(private readonly ReadingUpload $upload)
    {
        $this->batchSize = max(50, (int) config('uploads.import_batch_rows', 500));
    }

    public function run(): void
    {
        $disk = (string) config('uploads.disk', 'local');
        $relative = (string) $this->upload->path;
        if ($relative === '' || ! Storage::disk($disk)->exists($relative)) {
            throw new \RuntimeException('Uploaded file is missing from storage.');
        }

        $absolute = Storage::disk($disk)->path($relative);
        $extension = strtolower(pathinfo((string) $this->upload->original_name, PATHINFO_EXTENSION) ?: pathinfo($relative, PATHINFO_EXTENSION));

        if (StreamingSheetReader::isLegacyXls($extension)) {
            throw new \RuntimeException(StreamingSheetReader::LEGACY_XLS_MESSAGE);
        }

        $type = (string) $this->upload->type;
        $table = ReadingUpload::readingTable($type);

        $this->purgeExistingRows($table);

        $reader = new StreamingSheetReader($absolute, $extension);
        $mapping = null;
        $buffer = [];

        foreach ($reader->rows() as $row) {
            if ($mapping === null) {
                $headers = array_values(array_map(static fn ($c) => trim((string) $c), $row));
                $mapping = ReadingHeaderMap::map($type, $headers);
                $this->upload->forceFill([
                    'headers_json' => $headers,
                ])->save();

                if ($mapping['fields'] === []) {
                    throw new \RuntimeException('No recognisable columns in the header row — expected at least '.implode(' / ', ReadingHeaderMap::requiredFor($type)).'.');
                }

                continue;
            }

            if ($this->isBlankRow($row)) {
                continue;
            }

            $this->rowsTotal++;
            $staged = $this->stageRow($type, $row, $mapping);

            if ($staged === null) {
                $this->rowsFailed++;

                continue;
            }

            $buffer[] = $staged;

            if (count($buffer) >= $this->batchSize) {
                $this->flush($type, $table, $buffer);
                $buffer = [];
                $this->reportProgress();
            }
        }

        if ($buffer !== []) {
            $this->flush($type, $table, $buffer);
        }

        if ($mapping === null) {
            throw new \RuntimeException('The file appears to be empty — no header row found.');
        }

        $this->reportProgress();
    }

    public function rowsTotal(): int
    {
        return $this->rowsTotal;
    }

    public function rowsImported(): int
    {
        return $this->rowsImported;
    }

    public function rowsFailed(): int
    {
        return $this->rowsFailed;
    }

    /** Re-running an import must not duplicate rows. */
    private function purgeExistingRows(string $table): void
    {
        do {
            $deleted = DB::table($table)
                ->where('reading_upload_id', $this->upload->id)
                ->limit(5000)
                ->delete();
        } while ($deleted > 0);
    }

    /**
     * @param  list<string>  $row
     * @param  array{fields: array<string, int>, extras: array<int, string>}  $mapping
     * @return array<string, mixed>|null
     */
    private function stageRow(string $type, array $row, array $mapping): ?array
    {
        $value = static function (string $field) use ($mapping, $row): ?string {
            $index = $mapping['fields'][$field] ?? null;
            if ($index === null) {
                return null;
            }
            $cell = $row[$index] ?? null;
            $cell = $cell === null ? '' : trim((string) $cell);

            return $cell === '' ? null : $cell;
        };

        $required = ReadingHeaderMap::requiredFor($type);
        $hasKey = false;
        foreach ($required as $field) {
            if ($value($field) !== null) {
                $hasKey = true;
                break;
            }
        }
        if (! $hasKey) {
            return null;
        }

        $extras = [];
        foreach ($mapping['extras'] as $index => $label) {
            $cell = trim((string) ($row[$index] ?? ''));
            if ($cell !== '') {
                $extras[$label] = $cell;
            }
        }

        $common = [
            'reading_date' => ReadingHeaderMap::toDate($value('reading_date')) ?? $this->upload->period_from?->format('Y-m-d'),
            'period_label' => $value('period_label') ?? $this->upload->period_label,
            'kwh_import' => ReadingHeaderMap::toDecimal($value('kwh_import')),
            'kwh_export' => ReadingHeaderMap::toDecimal($value('kwh_export')),
            'kvah' => ReadingHeaderMap::toDecimal($value('kvah')),
            'md_kw' => ReadingHeaderMap::toDecimal($value('md_kw')),
            'raw_json' => $extras === [] ? null : json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return match ($type) {
            ReadingUpload::TYPE_FEEDER => $common + [
                'feeder_code' => self::clip($value('feeder_code'), 64) ?? '',
                'feeder_name' => self::clip($value('feeder_name'), 191),
            ],
            ReadingUpload::TYPE_DTR => $common + [
                'dtr_code' => self::clip($value('dtr_code'), 64) ?? '',
                'dtr_name' => self::clip($value('dtr_name'), 191),
                'feeder_code' => self::clip($value('feeder_code'), 64),
            ],
            ReadingUpload::TYPE_CONSUMER => $common + [
                'ivrs' => self::clip($value('ivrs'), 64),
                'msn' => self::clip($value('msn'), 64),
                'account_no' => self::clip($value('account_no'), 64),
                'consumer_name' => self::clip($value('consumer_name'), 191),
                'dtr_code' => self::clip($value('dtr_code'), 64),
                'feeder_code' => self::clip($value('feeder_code'), 64),
            ],
            default => throw new \InvalidArgumentException("Unknown reading type [{$type}]."),
        };
    }

    /** @param  list<array<string, mixed>>  $buffer */
    private function flush(string $type, string $table, array $buffer): void
    {
        if ($buffer === []) {
            return;
        }

        $now = now();
        $resolver = match ($type) {
            ReadingUpload::TYPE_FEEDER => fn (array $rows) => $this->attachFeederIds($rows),
            ReadingUpload::TYPE_DTR => fn (array $rows) => $this->attachDtrIds($rows),
            ReadingUpload::TYPE_CONSUMER => fn (array $rows) => $this->attachConsumerIds($rows),
            default => static fn (array $rows) => $rows,
        };

        $rows = $resolver($buffer);

        foreach ($rows as $index => $row) {
            $rows[$index] = $row + [
                'reading_upload_id' => $this->upload->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table($table)->insert($rows);
        $this->rowsImported += count($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachFeederIds(array $rows): array
    {
        $codes = self::distinctValues($rows, 'feeder_code');
        $map = $codes === [] ? [] : self::keyByUpper(
            Feeder::query()->whereIn('code', $codes)->pluck('id', 'code')->all()
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['feeder_id'] = $map[strtoupper((string) $row['feeder_code'])] ?? null;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachDtrIds(array $rows): array
    {
        $codes = self::distinctValues($rows, 'dtr_code');
        $map = $codes === [] ? [] : self::keyByUpper(
            Dtr::query()->whereIn('code', $codes)->pluck('id', 'code')->all()
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['dtr_id'] = $map[strtoupper((string) $row['dtr_code'])] ?? null;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachConsumerIds(array $rows): array
    {
        $maps = [];
        foreach (['ivrs', 'msn', 'account_no'] as $column) {
            $values = self::distinctValues($rows, $column);
            $maps[$column] = $values === [] ? [] : self::keyByUpper(
                Consumer::query()->whereIn($column, $values)->pluck('id', $column)->all()
            );
        }

        foreach ($rows as $index => $row) {
            $consumerId = null;
            foreach (['ivrs', 'msn', 'account_no'] as $column) {
                $value = $row[$column] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $consumerId = $maps[$column][strtoupper((string) $value)] ?? null;
                if ($consumerId !== null) {
                    break;
                }
            }
            $rows[$index]['consumer_id'] = $consumerId;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private static function distinctValues(array $rows, string $column): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if (is_string($value) && $value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * @param  array<string, mixed>  $pairs
     * @return array<string, int>
     */
    private static function keyByUpper(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $code => $id) {
            $out[strtoupper((string) $code)] = (int) $id;
        }

        return $out;
    }

    private static function clip(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $length);
    }

    /** @param  list<string>  $row */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function reportProgress(): void
    {
        $this->upload->forceFill([
            'rows_total' => $this->rowsTotal,
            'rows_imported' => $this->rowsImported,
            'rows_failed' => $this->rowsFailed,
        ])->save();
    }
}
