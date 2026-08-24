<?php

namespace App\Console\Commands;

use App\Models\Circle;
use App\Models\Consumer;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\Region;
use App\Models\Substation;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Add Consumer Master Excel rows (source=master) without wiping MI (source=mi).
 * On IVRS collision with an existing row, keep the existing (prefer MI) and skip.
 */
class ImportConsumerMaster extends Command
{
    protected $signature = 'seas:import-consumer-master
                            {--skip-excel : Use existing NDJSON only}
                            {--xlsx= : Absolute path to Excel (optional)}';

    protected $description = 'Import New Scope Consumer Master Excel as source=master (keep MI consumers)';

    /** @var array<string, int> */
    private array $regionIds = [];

    /** @var array<string, int> */
    private array $circleIds = [];

    /** @var array<string, int> */
    private array $divisionIds = [];

    /** @var array<string, int> */
    private array $zoneIds = [];

    /** @var array<string, int> */
    private array $substationIds = [];

    /** @var array<string, int> */
    private array $feederIds = [];

    /** @var array<string, int> */
    private array $dtrIds = [];

    /** @var array<string, true> */
    private array $existingIvrs = [];

    /** @var array<string, true> */
    private array $seenMasterIvrs = [];

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $imports = storage_path('app/imports');
        $ndjson = $imports.DIRECTORY_SEPARATOR.'consumers_scope_master.ndjson';
        $xlsx = $this->option('xlsx')
            ?: $imports.DIRECTORY_SEPARATOR.'New_Scope_Revised_Updated_Consumer_Master_List.xlsx';

        if (! $this->option('skip-excel')) {
            if (! is_file($xlsx)) {
                $this->error("Missing Excel: {$xlsx}");

                return self::FAILURE;
            }
            $this->info('Converting Excel → NDJSON…');
            $script = base_path('scripts/consumer_master_to_ndjson.py');
            $result = Process::path(base_path())
                ->timeout(7200)
                ->run(['py', '-3', $script, $xlsx, $ndjson]);
            if (! $result->successful()) {
                $this->error($result->errorOutput() ?: $result->output());

                return self::FAILURE;
            }
            $this->line(trim($result->output()));
        }

        if (! is_file($ndjson)) {
            $this->error("Missing NDJSON: {$ndjson}");

            return self::FAILURE;
        }

        if (! Schema::hasColumn('consumers', 'source')) {
            $this->error('Run migrations first (consumers.source missing).');

            return self::FAILURE;
        }

        try {
            DB::statement('PRAGMA busy_timeout = 120000');
            DB::statement('PRAGMA journal_mode = WAL');
            DB::statement('PRAGMA synchronous = NORMAL');
            DB::statement('PRAGMA temp_store = MEMORY');
        } catch (\Throwable $e) {
            $this->warn('SQLite pragma setup: '.$e->getMessage());
        }

        $this->loadLookups();
        $this->info('Importing master consumers (source=master)…');

        $added = 0;
        $skippedIvrs = 0;
        $skippedNoDtr = 0;
        $buffer = [];
        $now = now()->toDateTimeString();

        foreach ($this->readNdjson($ndjson) as $row) {
            $ivrs = $this->str($row['IVRS Number'] ?? null);
            if ($ivrs !== null) {
                if (isset($this->existingIvrs[$ivrs]) || isset($this->seenMasterIvrs[$ivrs])) {
                    $skippedIvrs++;

                    continue;
                }
            }

            $dtrId = $this->resolveDtrId($row);
            if ($dtrId === null) {
                $skippedNoDtr++;

                continue;
            }

            $feederCode = $this->str($row['Feeder Code'] ?? null);
            $feederId = null;
            if ($feederCode !== null && isset($this->feederIds[$feederCode])) {
                $feederId = $this->feederIds[$feederCode];
            } else {
                $feederId = Dtr::query()->where('id', $dtrId)->value('feeder_id');
            }

            $buffer[] = [
                'dtr_id' => $dtrId,
                'feeder_id' => $feederId,
                'pole_id' => null,
                'name' => $this->str($row['Consumer Name'] ?? null),
                'phone' => $this->str($row['Mobile Number'] ?? null),
                'ivrs' => $ivrs,
                'account_no' => null,
                'msn' => $this->str($row['New Meter Serial Number'] ?? null),
                'address' => $this->str($row['address'] ?? null),
                'phase' => $this->str($row['phase'] ?? null),
                'is_active' => 1,
                'source' => 'master',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($ivrs !== null) {
                $this->seenMasterIvrs[$ivrs] = true;
                $this->existingIvrs[$ivrs] = true;
            }
            $added++;

            if (count($buffer) >= 500) {
                $this->insertConsumersWithRetry($buffer);
                $buffer = [];
                if ($added % 50000 === 0) {
                    $this->line("  … added {$added}");
                }
            }
        }

        if ($buffer !== []) {
            $this->insertConsumersWithRetry($buffer);
        }

        $counts = [
            'mi' => Consumer::query()->where('source', 'mi')->count(),
            'master' => Consumer::query()->where('source', 'master')->count(),
            'total' => Consumer::query()->count(),
            'dtrs' => Dtr::query()->count(),
            'feeders' => Feeder::query()->count(),
        ];

        $this->newLine();
        $this->info('Master consumer import complete:');
        $this->line("  added (master):     {$added}");
        $this->line("  skipped (IVRS hit): {$skippedIvrs}");
        $this->line("  skipped (no DTR):   {$skippedNoDtr}");
        foreach ($counts as $label => $n) {
            $this->line(sprintf('  %-14s %d', $label, $n));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $buffer
     */
    private function insertConsumersWithRetry(array $buffer, int $attempts = 8): void
    {
        $delayMs = 200;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                Consumer::query()->insert($buffer);

                return;
            } catch (\Throwable $e) {
                $locked = str_contains(strtolower($msg), 'database is locked');
                if (! $locked || $i === $attempts) {
                    throw $e;
                }
                usleep($delayMs * 1000);
                $delayMs = min(5000, $delayMs * 2);
            }
        }
    }

    private function loadLookups(): void
    {
        $this->info('Loading feeder/DTR/IVRS lookups…');

        Feeder::query()->orderBy('id')->chunkById(2000, function ($rows) {
            foreach ($rows as $row) {
                $this->feederIds[$row->code] = (int) $row->id;
            }
        });

        Dtr::query()->orderBy('id')->chunkById(5000, function ($rows) {
            foreach ($rows as $row) {
                $this->dtrIds[$row->code] = (int) $row->id;
            }
        });

        Consumer::query()->whereNotNull('ivrs')->orderBy('id')->chunkById(10000, function ($rows) {
            foreach ($rows as $row) {
                $ivrs = trim((string) $row->ivrs);
                if ($ivrs !== '') {
                    $this->existingIvrs[$ivrs] = true;
                }
            }
        });

        // Prefill hierarchy maps from DB so we don't duplicate parents.
        Region::query()->get(['id', 'name'])->each(function ($r) {
            $this->regionIds[$r->name] = (int) $r->id;
        });
        Circle::query()->get(['id', 'region_id', 'name'])->each(function ($c) {
            $this->circleIds[$c->region_id.'|'.$c->name] = (int) $c->id;
        });
        Division::query()->get(['id', 'circle_id', 'name'])->each(function ($d) {
            $this->divisionIds[$d->circle_id.'|'.$d->name] = (int) $d->id;
        });
        Zone::query()->get(['id', 'division_id', 'name'])->each(function ($z) {
            $this->zoneIds[$z->division_id.'|'.$z->name] = (int) $z->id;
        });
        Substation::query()->get(['id', 'zone_id', 'name'])->each(function ($s) {
            $this->substationIds[$s->zone_id.'|'.$s->name] = (int) $s->id;
        });

        $this->line('  feeders: '.count($this->feederIds).', dtrs: '.count($this->dtrIds).', ivrs: '.count($this->existingIvrs));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveDtrId(array $row): ?int
    {
        $dtrCode = $this->str($row['DTR Code'] ?? null);
        if ($dtrCode !== null && isset($this->dtrIds[$dtrCode]) && $this->dtrIds[$dtrCode] > 0) {
            return $this->dtrIds[$dtrCode];
        }

        $feederCode = $this->str($row['Feeder Code'] ?? null);
        $feederName = $this->str($row['Feeder Name'] ?? null);
        $dtrName = $this->str($row['DTR Name'] ?? null) ?? $dtrCode;

        if ($dtrCode === null) {
            return null;
        }

        // Need a feeder to attach the new DTR
        if ($feederCode === null || ! isset($this->feederIds[$feederCode]) || $this->feederIds[$feederCode] < 1) {
            $substationId = $this->ensureHierarchy($row);
            if ($substationId === null || $feederCode === null) {
                return null;
            }
            $feeder = Feeder::query()->create([
                'substation_id' => $substationId,
                'code' => $feederCode,
                'name' => $feederName ?? $feederCode,
                'is_active' => true,
            ]);
            $this->feederIds[$feederCode] = $feeder->id;
        }

        $attempts = 8;
        $delayMs = 200;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $dtr = Dtr::query()->create([
                    'feeder_id' => $this->feederIds[$feederCode],
                    'code' => $dtrCode,
                    'name' => $dtrName ?? $dtrCode,
                    'capacity_kva' => null,
                    'is_active' => true,
                ]);
                $this->dtrIds[$dtrCode] = $dtr->id;

                return $dtr->id;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $existing = Dtr::query()->where('code', $dtrCode)->first();
                if ($existing) {
                    $this->dtrIds[$dtrCode] = $existing->id;

                    return $existing->id;
                }
                throw $e;
            } catch (\Throwable $e) {
                $locked = str_contains(strtolower($msg), 'database is locked');
                if (! $locked || $i === $attempts) {
                    throw $e;
                }
                usleep($delayMs * 1000);
                $delayMs = min(5000, $delayMs * 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ensureHierarchy(array $row): ?int
    {
        $region = $this->str($row['Region'] ?? null);
        $circle = $this->str($row['Circle'] ?? null);
        $division = $this->str($row['Division'] ?? null);
        $zone = $this->str($row['Zone'] ?? null);
        if ($region === null || $circle === null || $division === null || $zone === null) {
            return null;
        }

        if (! isset($this->regionIds[$region])) {
            $this->regionIds[$region] = Region::query()->create([
                'name' => $region,
                'is_active' => true,
            ])->id;
        }
        $regionId = $this->regionIds[$region];

        $circleKey = $regionId.'|'.$circle;
        if (! isset($this->circleIds[$circleKey])) {
            $this->circleIds[$circleKey] = Circle::query()->create([
                'region_id' => $regionId,
                'name' => $circle,
                'is_active' => true,
            ])->id;
        }
        $circleId = $this->circleIds[$circleKey];

        $divisionKey = $circleId.'|'.$division;
        if (! isset($this->divisionIds[$divisionKey])) {
            $this->divisionIds[$divisionKey] = Division::query()->create([
                'circle_id' => $circleId,
                'name' => $division,
                'is_active' => true,
            ])->id;
        }
        $divisionId = $this->divisionIds[$divisionKey];

        $zoneKey = $divisionId.'|'.$zone;
        if (! isset($this->zoneIds[$zoneKey])) {
            $this->zoneIds[$zoneKey] = Zone::query()->create([
                'division_id' => $divisionId,
                'name' => $zone,
                'is_active' => true,
            ])->id;
        }
        $zoneId = $this->zoneIds[$zoneKey];

        $subName = $this->str($row['Substation Name'] ?? null) ?? ($zone.' SS');
        $subKey = $zoneId.'|'.$subName;
        if (! isset($this->substationIds[$subKey])) {
            $existing = Substation::query()
                ->where('zone_id', $zoneId)
                ->where('name', $subName)
                ->first();
            if ($existing) {
                $this->substationIds[$subKey] = $existing->id;
            } else {
                $this->substationIds[$subKey] = Substation::query()->create([
                    'zone_id' => $zoneId,
                    'name' => $subName,
                    'is_active' => true,
                ])->id;
            }
        }

        return $this->substationIds[$subKey];
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function readNdjson(string $path): \Generator
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot open {$path}");
        }
        try {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            if (is_float($value) && floor($value) != $value) {
                return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
            }

            return (string) (int) $value;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
