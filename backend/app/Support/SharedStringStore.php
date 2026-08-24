<?php

namespace App\Support;

/**
 * Random-access store for an XLSX shared-strings table.
 *
 * Small workbooks keep every string in a PHP array. Once the accumulated bytes
 * pass the memory budget the store spools to two temp files — a flat data file
 * plus a fixed-width index of (offset, length) pairs — so a 300 MB workbook with
 * millions of distinct strings still resolves lookups in O(1) with bounded RAM.
 */
class SharedStringStore
{
    private const INDEX_RECORD_BYTES = 16;

    /** @var list<string> */
    private array $memory = [];

    private bool $spooled = false;

    private int $count = 0;

    private int $memoryBytes = 0;

    private int $dataBytes = 0;

    /** @var resource|null */
    private $dataHandle = null;

    /** @var resource|null */
    private $indexHandle = null;

    private ?string $dataPath = null;

    private ?string $indexPath = null;

    public function __construct(
        private readonly int $memoryBudgetBytes = 4194304,
        private readonly ?string $tempDir = null,
    ) {}

    public function push(string $value): void
    {
        if (! $this->spooled) {
            $this->memoryBytes += strlen($value) + 16;
            if ($this->memoryBytes <= $this->memoryBudgetBytes) {
                $this->memory[] = $value;
                $this->count++;

                return;
            }
            $this->spill();
        }

        $this->append($value);
        $this->count++;
    }

    public function get(int $index): string
    {
        if ($index < 0 || $index >= $this->count) {
            return '';
        }

        if (! $this->spooled) {
            return $this->memory[$index] ?? '';
        }

        if ($this->indexHandle === null || $this->dataHandle === null) {
            return '';
        }

        if (fseek($this->indexHandle, $index * self::INDEX_RECORD_BYTES) !== 0) {
            return '';
        }
        $record = fread($this->indexHandle, self::INDEX_RECORD_BYTES);
        if ($record === false || strlen($record) < self::INDEX_RECORD_BYTES) {
            return '';
        }
        /** @var array{offset: int, length: int}|false $parts */
        $parts = unpack('Poffset/Plength', $record);
        if ($parts === false || $parts['length'] < 1) {
            return '';
        }

        if (fseek($this->dataHandle, $parts['offset']) !== 0) {
            return '';
        }
        $value = fread($this->dataHandle, $parts['length']);

        return $value === false ? '' : $value;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function isSpooled(): bool
    {
        return $this->spooled;
    }

    /** Flush write buffers so subsequent reads see everything that was pushed. */
    public function finalize(): void
    {
        if ($this->dataHandle !== null) {
            fflush($this->dataHandle);
        }
        if ($this->indexHandle !== null) {
            fflush($this->indexHandle);
        }
    }

    public function close(): void
    {
        foreach ([$this->dataHandle, $this->indexHandle] as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        $this->dataHandle = null;
        $this->indexHandle = null;

        foreach ([$this->dataPath, $this->indexPath] as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
        $this->dataPath = null;
        $this->indexPath = null;

        $this->memory = [];
    }

    public function __destruct()
    {
        $this->close();
    }

    private function spill(): void
    {
        $dir = $this->resolveTempDir();
        $this->dataPath = $dir.DIRECTORY_SEPARATOR.'seas_ss_'.uniqid('', true).'.dat';
        $this->indexPath = $dir.DIRECTORY_SEPARATOR.'seas_ss_'.uniqid('', true).'.idx';

        $data = fopen($this->dataPath, 'w+b');
        $index = fopen($this->indexPath, 'w+b');
        if ($data === false || $index === false) {
            if (is_resource($data)) {
                fclose($data);
            }
            if (is_resource($index)) {
                fclose($index);
            }
            throw new \RuntimeException('Unable to create shared-string spool files.');
        }

        $this->dataHandle = $data;
        $this->indexHandle = $index;
        $this->spooled = true;

        foreach ($this->memory as $value) {
            $this->append($value);
        }
        $this->memory = [];
        $this->memoryBytes = 0;
    }

    private function append(string $value): void
    {
        if ($this->dataHandle === null || $this->indexHandle === null) {
            return;
        }

        $length = strlen($value);
        if ($length > 0) {
            fwrite($this->dataHandle, $value);
        }
        fwrite($this->indexHandle, pack('PP', $this->dataBytes, $length));
        $this->dataBytes += $length;
    }

    private function resolveTempDir(): string
    {
        $candidates = [
            $this->tempDir,
            function_exists('storage_path') ? storage_path('app/private/tmp') : null,
            sys_get_temp_dir(),
        ];

        foreach ($candidates as $dir) {
            if (! is_string($dir) || $dir === '') {
                continue;
            }
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return rtrim($dir, DIRECTORY_SEPARATOR);
            }
        }

        throw new \RuntimeException('No writable temp directory for shared-string spooling.');
    }
}
