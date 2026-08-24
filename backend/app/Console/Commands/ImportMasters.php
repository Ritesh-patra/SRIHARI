<?php

namespace App\Console\Commands;

use App\Models\Circle;
use App\Models\Consumer;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\Region;
use App\Models\Substation;
use App\Models\User;
use App\Models\UserScope;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Import Feeder_Master_Final + DT_Master_Final + Consumer MI Done into MASTER tables.
 * Survey tables are wiped for clean FKs but never written by this command.
 */
class ImportMasters extends Command
{
    protected $signature = 'seas:import-masters
                            {--skip-excel : Use existing NDJSON files only}
                            {--keep-surveys : Do not truncate survey / assignment tables}';

    protected $description = 'Wipe sample masters and import Feeder/DT Final + Consumer MI Done (NDJSON stream)';

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

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $imports = storage_path('app/imports');

        if (! $this->option('skip-excel')) {
            $needed = [
                $imports.DIRECTORY_SEPARATOR.'Feeder_Master_Final.xlsx',
                $imports.DIRECTORY_SEPARATOR.'DT_Master_Final.xlsx',
                $imports.DIRECTORY_SEPARATOR.'Consumer_MI_Done_Till_28_July_2026.xlsx',
            ];
            foreach ($needed as $xlsx) {
                if (! is_file($xlsx)) {
                    $this->error("Missing Excel: {$xlsx}");

                    return self::FAILURE;
                }
            }

            $this->info('Converting Excel → NDJSON (py -3 scripts/masters_to_json.py)…');
            $script = base_path('scripts/masters_to_json.py');
            $result = Process::path(base_path())
                ->timeout(3600)
                ->run(['py', '-3', $script]);
            if (! $result->successful()) {
                $this->error($result->errorOutput() ?: $result->output());

                return self::FAILURE;
            }
            $this->line(trim($result->output()));
        }

        $feederPath = $imports.DIRECTORY_SEPARATOR.'feeders_master.ndjson';
        $dtrPath = $imports.DIRECTORY_SEPARATOR.'dtrs_master.ndjson';
        $consumerPath = $imports.DIRECTORY_SEPARATOR.'consumers_master.ndjson';

        foreach ([$feederPath, $dtrPath, $consumerPath] as $path) {
            if (! is_file($path)) {
                $this->error("Missing NDJSON: {$path}");

                return self::FAILURE;
            }
        }

        $this->ensureDemoUsers();

        Schema::disableForeignKeyConstraints();
        try {
            $this->wipeMasterData();
            $this->importFeeders($feederPath);
            $this->importDtrs($dtrPath);
            $this->importConsumers($consumerPath);
            $this->assignRegionScopes();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $counts = [
            'regions' => Region::query()->count(),
            'circles' => Circle::query()->count(),
            'divisions' => Division::query()->count(),
            'zones' => Zone::query()->count(),
            'substations' => Substation::query()->count(),
            'feeders' => Feeder::query()->count(),
            'dtrs' => Dtr::query()->count(),
            'consumers' => Consumer::query()->count(),
        ];

        $this->newLine();
        $this->info('Import complete (MASTER only — surveys not written):');
        foreach ($counts as $label => $n) {
            $this->line(sprintf('  %-14s %d', $label, $n));
        }

        return self::SUCCESS;
    }

    private function wipeMasterData(): void
    {
        $this->warn('Truncating surveys + master hierarchy (users kept)…');

        $tables = [
            'feeder_survey_sld_photos',
            'consumer_surveys',
            'feeder_surveys',
            'dtr_surveys',
            'work_assignments',
            'consumers',
            'poles',
            'dtrs',
            'feeders',
            'substations',
            'zones',
            'divisions',
            'circles',
            'regions',
            'user_scopes',
        ];

        if ($this->option('keep-surveys')) {
            $tables = array_values(array_diff($tables, [
                'feeder_survey_sld_photos',
                'consumer_surveys',
                'feeder_surveys',
                'dtr_surveys',
                'work_assignments',
            ]));
        }

        $driver = Schema::getConnection()->getDriverName();
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($driver === 'sqlite') {
                DB::table($table)->delete();
                try {
                    DB::statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
                } catch (\Throwable) {
                    // no sqlite_sequence row
                }
            } else {
                DB::table($table)->truncate();
            }
        }
    }

    private function importFeeders(string $path): void
    {
        $this->info('Importing feeders from Feeder_Master_Final…');
        $n = 0;
        $buffer = [];
        $now = now()->toDateTimeString();

        foreach ($this->readNdjson($path) as $row) {
            $substationId = $this->ensureHierarchy($row);
            $code = $this->str($row['Feeder Code'] ?? null);
            $name = $this->str($row['Feeder Name'] ?? null) ?? $code;
            if ($substationId === null || $code === null) {
                continue;
            }
            if (isset($this->feederIds[$code])) {
                continue;
            }

            $buffer[] = [
                'substation_id' => $substationId,
                'code' => $code,
                'name' => $name ?? $code,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            // Reserve key; id filled after flush via re-query map — use temp then remap
            $this->feederIds[$code] = -1;
            $n++;

            if (count($buffer) >= 500) {
                $this->flushFeeders($buffer);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            $this->flushFeeders($buffer);
        }

        $this->line("  feeders inserted/seen: {$n} (unique codes: ".count($this->feederIds).')');
    }

    /**
     * @param  list<array<string, mixed>>  $buffer
     */
    private function flushFeeders(array $buffer): void
    {
        Feeder::query()->insert($buffer);
        $codes = array_column($buffer, 'code');
        $rows = Feeder::query()->whereIn('code', $codes)->get(['id', 'code']);
        foreach ($rows as $row) {
            $this->feederIds[$row->code] = (int) $row->id;
        }
    }

    private function importDtrs(string $path): void
    {
        $this->info('Importing DTRs from DT_Master_Final…');
        $n = 0;
        $buffer = [];
        $now = now()->toDateTimeString();

        foreach ($this->readNdjson($path) as $row) {
            $substationId = $this->ensureHierarchy($row);
            $feederCode = $this->str($row['Feeder Code'] ?? null);
            $feederName = $this->str($row['Feeder Name'] ?? null);
            $dtrCode = $this->str($row['DTR Code'] ?? null);
            $dtrName = $this->str($row['DTR Name'] ?? null) ?? $dtrCode;
            if ($substationId === null || $feederCode === null || $dtrCode === null) {
                continue;
            }
            if (isset($this->dtrIds[$dtrCode])) {
                continue;
            }

            if (! isset($this->feederIds[$feederCode]) || $this->feederIds[$feederCode] < 1) {
                $feeder = Feeder::query()->create([
                    'substation_id' => $substationId,
                    'code' => $feederCode,
                    'name' => $feederName ?? $feederCode,
                    'is_active' => true,
                ]);
                $this->feederIds[$feederCode] = $feeder->id;
            }

            $capacity = $row['DTR Capacity in kVA'] ?? null;
            $capacityKva = is_numeric($capacity) ? (int) $capacity : null;

            $buffer[] = [
                'feeder_id' => $this->feederIds[$feederCode],
                'code' => $dtrCode,
                'name' => $dtrName ?? $dtrCode,
                'capacity_kva' => $capacityKva,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->dtrIds[$dtrCode] = -1;
            $n++;

            if (count($buffer) >= 500) {
                $this->flushDtrs($buffer);
                $buffer = [];
                if ($n % 25000 === 0) {
                    $this->line("  … dtrs {$n}");
                }
            }
        }
        if ($buffer !== []) {
            $this->flushDtrs($buffer);
        }

        $this->line("  dtrs inserted/seen: {$n} (unique codes: ".count($this->dtrIds).')');
    }

    /**
     * @param  list<array<string, mixed>>  $buffer
     */
    private function flushDtrs(array $buffer): void
    {
        Dtr::query()->insert($buffer);
        $codes = array_column($buffer, 'code');
        $rows = Dtr::query()->whereIn('code', $codes)->get(['id', 'code']);
        foreach ($rows as $row) {
            $this->dtrIds[$row->code] = (int) $row->id;
        }
    }

    private function importConsumers(string $path): void
    {
        $this->info('Importing consumers from Consumer MI (center list)…');
        $n = 0;
        $skipped = 0;
        $buffer = [];
        $now = now()->toDateTimeString();

        foreach ($this->readNdjson($path) as $row) {
            $feederCode = $this->str($row['Feeder Code'] ?? null);
            $feederName = $this->str($row['Feeder Name'] ?? null);
            $dtrCode = $this->str($row['DTR Code'] ?? null);
            $dtrName = $this->str($row['DTR Name'] ?? null) ?? $dtrCode;

            if ($dtrCode === null) {
                $skipped++;

                continue;
            }

            // Ensure DTR (+ feeder/hierarchy) exists when MI list has codes not in Final DT
            if (! isset($this->dtrIds[$dtrCode]) || $this->dtrIds[$dtrCode] < 1) {
                $substationId = $this->ensureHierarchy($row);
                if ($substationId === null || $feederCode === null) {
                    $skipped++;

                    continue;
                }
                if (! isset($this->feederIds[$feederCode]) || $this->feederIds[$feederCode] < 1) {
                    $feeder = Feeder::query()->create([
                        'substation_id' => $substationId,
                        'code' => $feederCode,
                        'name' => $feederName ?? $feederCode,
                        'is_active' => true,
                    ]);
                    $this->feederIds[$feederCode] = $feeder->id;
                }
                $dtr = Dtr::query()->create([
                    'feeder_id' => $this->feederIds[$feederCode],
                    'code' => $dtrCode,
                    'name' => $dtrName ?? $dtrCode,
                    'capacity_kva' => null,
                    'is_active' => true,
                ]);
                $this->dtrIds[$dtrCode] = $dtr->id;
            }

            $feederId = null;
            if ($feederCode !== null && isset($this->feederIds[$feederCode]) && $this->feederIds[$feederCode] > 0) {
                $feederId = $this->feederIds[$feederCode];
            } else {
                // Fall back to DTR's feeder
                $feederId = Dtr::query()->where('id', $this->dtrIds[$dtrCode])->value('feeder_id');
            }

            $buffer[] = [
                'dtr_id' => $this->dtrIds[$dtrCode],
                'feeder_id' => $feederId,
                'pole_id' => null,
                'name' => $this->str($row['Consumer Name'] ?? null),
                'phone' => $this->str($row['Mobile Number'] ?? null),
                'ivrs' => $this->str($row['IVRS Number'] ?? null),
                'account_no' => null,
                'msn' => $this->str($row['New Meter Serial Number'] ?? null),
                'address' => null,
                'phase' => $this->str($row['New Meter Type'] ?? null),
                'is_active' => 1,
                'source' => 'mi',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $n++;

            if (count($buffer) >= 500) {
                Consumer::query()->insert($buffer);
                $buffer = [];
                if ($n % 50000 === 0) {
                    $this->line("  … consumers {$n}");
                }
            }
        }
        if ($buffer !== []) {
            Consumer::query()->insert($buffer);
        }

        $this->line("  consumers inserted: {$n} (skipped: {$skipped})");
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
                if (! is_array($row)) {
                    continue;
                }
                yield $row;
            }
        } finally {
            fclose($fh);
        }
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

        $subName = $this->str($row['Substation Name'] ?? null);
        $subCode = $this->str($row['Substation Code'] ?? null);
        if ($subName === null) {
            $subName = $subCode ?? 'Unknown Substation';
        }

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

    private function assignRegionScopes(): void
    {
        $regionIds = Region::query()->pluck('id');
        if ($regionIds->isEmpty()) {
            return;
        }

        $emails = [
            'pm@seas.test',
            'manager@seas.test',
            'supervisor@seas.test',
            'surveyor@seas.test',
            'fe@seas.test',
        ];

        $users = User::query()->whereIn('email', $emails)->get();
        foreach ($users as $user) {
            UserScope::query()->where('user_id', $user->id)->delete();
            foreach ($regionIds as $regionId) {
                UserScope::query()->create([
                    'user_id' => $user->id,
                    'scope_type' => 'region',
                    'scope_id' => $regionId,
                ]);
            }
        }

        $this->info('UserScope: region access granted to PM / manager / FE users.');
    }

    private function ensureDemoUsers(): void
    {
        $password = 'password';

        User::query()->updateOrCreate(
            ['email' => 'super@seas.test'],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'role' => User::ROLE_SUPER_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@seas.test'],
            [
                'name' => 'Admin SEAS',
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'pm@seas.test'],
            [
                'name' => 'Project Manager Neha',
                'password' => $password,
                'role' => User::ROLE_PROJECT_MANAGER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@seas.test'],
            [
                'name' => 'Manager Raj',
                'password' => $password,
                'role' => User::ROLE_MANAGER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'supervisor@seas.test'],
            [
                'name' => 'Supervisor Raj (alias)',
                'password' => $password,
                'role' => User::ROLE_MANAGER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'surveyor@seas.test'],
            [
                'name' => 'Field Executive Anuj',
                'password' => $password,
                'role' => User::ROLE_FIELD_EXECUTIVE,
                'supervisor_id' => $manager->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'fe@seas.test'],
            [
                'name' => 'Field Executive Riya',
                'password' => $password,
                'role' => User::ROLE_FIELD_EXECUTIVE,
                'supervisor_id' => $manager->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $this->info('Demo users ensured (password: password).');
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
