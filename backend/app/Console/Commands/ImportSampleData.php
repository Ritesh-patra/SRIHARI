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

class ImportSampleData extends Command
{
    protected $signature = 'seas:import-sample-data
                            {--skip-excel : Use existing JSON files only}
                            {--keep-surveys : Do not truncate survey / assignment tables}';

    protected $description = 'Wipe dummy master data and import Real Sample_Data.xlsx (via Python JSON)';

    public function handle(): int
    {
        $imports = storage_path('app/imports');
        $xlsx = $imports.DIRECTORY_SEPARATOR.'Sample_Data.xlsx';

        if (! $this->option('skip-excel')) {
            if (! is_file($xlsx)) {
                $this->error("Missing Excel: {$xlsx}");

                return self::FAILURE;
            }
            $this->info('Converting Excel → JSON (py -3 scripts/excel_to_json.py)…');
            $script = base_path('scripts/excel_to_json.py');
            $result = Process::path(base_path())
                ->timeout(300)
                ->run(['py', '-3', $script]);
            if (! $result->successful()) {
                $this->error($result->errorOutput() ?: $result->output());

                return self::FAILURE;
            }
            $this->line(trim($result->output()));
        }

        $feedersPath = $imports.DIRECTORY_SEPARATOR.'feeders.json';
        $dtrsPath = $imports.DIRECTORY_SEPARATOR.'dtrs.json';
        $consumersPath = $imports.DIRECTORY_SEPARATOR.'consumers.json';

        foreach ([$feedersPath, $dtrsPath, $consumersPath] as $path) {
            if (! is_file($path)) {
                $this->error("Missing JSON: {$path}");

                return self::FAILURE;
            }
        }

        $feederRows = json_decode((string) file_get_contents($feedersPath), true) ?: [];
        $dtrRows = json_decode((string) file_get_contents($dtrsPath), true) ?: [];
        $consumerRows = json_decode((string) file_get_contents($consumersPath), true) ?: [];

        $this->info(sprintf(
            'Loaded JSON: feeders=%d dtrs=%d consumers=%d',
            count($feederRows),
            count($dtrRows),
            count($consumerRows)
        ));

        $this->ensureDemoUsers();

        Schema::disableForeignKeyConstraints();
        try {
            $this->wipeMasterData();
            $counts = $this->importAll($feederRows, $dtrRows, $consumerRows);
            $this->assignRegionScopes();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info('Import complete:');
        foreach ($counts as $label => $n) {
            $this->line(sprintf('  %-14s %d', $label, $n));
        }

        return self::SUCCESS;
    }

    private function wipeMasterData(): void
    {
        $this->warn('Truncating survey + master hierarchy (users kept)…');

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

    /**
     * @param  list<array<string, mixed>>  $feederRows
     * @param  list<array<string, mixed>>  $dtrRows
     * @param  list<array<string, mixed>>  $consumerRows
     * @return array<string, int>
     */
    private function importAll(array $feederRows, array $dtrRows, array $consumerRows): array
    {
        /** @var array<string, int> $regionIds */
        $regionIds = [];
        /** @var array<string, int> $circleIds */
        $circleIds = [];
        /** @var array<string, int> $divisionIds */
        $divisionIds = [];
        /** @var array<string, int> $zoneIds */
        $zoneIds = [];
        /** @var array<string, int> $substationIds */
        $substationIds = [];
        /** @var array<string, int> $feederIds by feeder code */
        $feederIds = [];
        /** @var array<string, int> $dtrIds by dtr code */
        $dtrIds = [];

        $ensureHierarchy = function (array $row) use (
            &$regionIds,
            &$circleIds,
            &$divisionIds,
            &$zoneIds,
            &$substationIds
        ): ?int {
            $region = $this->str($row['Region'] ?? null);
            $circle = $this->str($row['Circle'] ?? null);
            $division = $this->str($row['Division'] ?? null);
            $zone = $this->str($row['Zone/DC'] ?? null);
            if ($region === null || $circle === null || $division === null || $zone === null) {
                return null;
            }

            if (! isset($regionIds[$region])) {
                $regionIds[$region] = Region::query()->create([
                    'name' => $region,
                    'is_active' => true,
                ])->id;
            }
            $regionId = $regionIds[$region];

            $circleKey = $regionId.'|'.$circle;
            if (! isset($circleIds[$circleKey])) {
                $circleIds[$circleKey] = Circle::query()->create([
                    'region_id' => $regionId,
                    'name' => $circle,
                    'is_active' => true,
                ])->id;
            }
            $circleId = $circleIds[$circleKey];

            $divisionKey = $circleId.'|'.$division;
            if (! isset($divisionIds[$divisionKey])) {
                $divisionIds[$divisionKey] = Division::query()->create([
                    'circle_id' => $circleId,
                    'name' => $division,
                    'is_active' => true,
                ])->id;
            }
            $divisionId = $divisionIds[$divisionKey];

            $zoneKey = $divisionId.'|'.$zone;
            if (! isset($zoneIds[$zoneKey])) {
                $zoneIds[$zoneKey] = Zone::query()->create([
                    'division_id' => $divisionId,
                    'name' => $zone,
                    'is_active' => true,
                ])->id;
            }
            $zoneId = $zoneIds[$zoneKey];

            $subName = $this->str(
                $row['Substation Name']
                    ?? $row['SubStation']
                    ?? $row['Substation']
                    ?? null
            );
            $subCode = $this->str(
                $row['Substation Code']
                    ?? $row['SubStation Code']
                    ?? null
            );
            if ($subName === null) {
                $subName = $subCode ?? 'Unknown Substation';
            }

            $subKey = $zoneId.'|'.$subName;
            if (! isset($substationIds[$subKey])) {
                // Prefer name uniqueness under zone; on collision append code.
                $existing = Substation::query()
                    ->where('zone_id', $zoneId)
                    ->where('name', $subName)
                    ->first();
                if ($existing) {
                    $substationIds[$subKey] = $existing->id;
                } else {
                    $nameToStore = $subName;
                    // If another substation somehow collided on key building, suffix code.
                    if ($subCode !== null && Substation::query()->where('zone_id', $zoneId)->where('name', $nameToStore)->exists()) {
                        $nameToStore = $subName.' ('.$subCode.')';
                    }
                    $substationIds[$subKey] = Substation::query()->create([
                        'zone_id' => $zoneId,
                        'name' => $nameToStore,
                        'is_active' => true,
                    ])->id;
                }
            }

            return $substationIds[$subKey];
        };

        $this->info('Importing feeders…');
        $bar = $this->output->createProgressBar(count($feederRows));
        $bar->start();
        foreach ($feederRows as $row) {
            $substationId = $ensureHierarchy($row);
            $code = $this->str($row['Feeder Code'] ?? null);
            $name = $this->str($row['Feeder'] ?? null) ?? $code;
            if ($substationId === null || $code === null) {
                $bar->advance();

                continue;
            }
            if (! isset($feederIds[$code])) {
                $feederIds[$code] = Feeder::query()->create([
                    'substation_id' => $substationId,
                    'code' => $code,
                    'name' => $name ?? $code,
                    'is_active' => true,
                ])->id;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info('Importing DTRs…');
        $bar = $this->output->createProgressBar(count($dtrRows));
        $bar->start();
        foreach ($dtrRows as $row) {
            $substationId = $ensureHierarchy($row);
            $feederCode = $this->str($row['Feeder Code'] ?? null);
            $feederName = $this->str($row['Feeder'] ?? null);
            $dtrCode = $this->str($row['DTR Code'] ?? null);
            $dtrName = $this->str($row['DTR Name'] ?? $row['DTR Name '] ?? null) ?? $dtrCode;
            if ($substationId === null || $feederCode === null || $dtrCode === null) {
                $bar->advance();

                continue;
            }
            if (! isset($feederIds[$feederCode])) {
                $feederIds[$feederCode] = Feeder::query()->create([
                    'substation_id' => $substationId,
                    'code' => $feederCode,
                    'name' => $feederName ?? $feederCode,
                    'is_active' => true,
                ])->id;
            }
            $capacity = $row['DTR Capacity in kVA'] ?? null;
            $capacityKva = is_numeric($capacity) ? (int) $capacity : null;
            if (! isset($dtrIds[$dtrCode])) {
                $dtrIds[$dtrCode] = Dtr::query()->create([
                    'feeder_id' => $feederIds[$feederCode],
                    'code' => $dtrCode,
                    'name' => $dtrName ?? $dtrCode,
                    'capacity_kva' => $capacityKva,
                    'is_active' => true,
                ])->id;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info('Importing consumers…');
        $bar = $this->output->createProgressBar(count($consumerRows));
        $bar->start();
        $consumerBuffer = [];
        $now = now()->toDateTimeString();
        $flush = function () use (&$consumerBuffer) {
            if ($consumerBuffer === []) {
                return;
            }
            Consumer::query()->insert($consumerBuffer);
            $consumerBuffer = [];
        };

        foreach ($consumerRows as $row) {
            $substationId = $ensureHierarchy($row);
            $feederCode = $this->str($row['Feeder Code'] ?? null);
            $feederName = $this->str($row['Feeder'] ?? null);
            $dtrCode = $this->str($row['DTR Code'] ?? null);
            $dtrName = $this->str($row['DTR Name'] ?? null) ?? $dtrCode;
            if ($substationId === null || $feederCode === null || $dtrCode === null) {
                $bar->advance();

                continue;
            }
            if (! isset($feederIds[$feederCode])) {
                $feederIds[$feederCode] = Feeder::query()->create([
                    'substation_id' => $substationId,
                    'code' => $feederCode,
                    'name' => $feederName ?? $feederCode,
                    'is_active' => true,
                ])->id;
            }
            if (! isset($dtrIds[$dtrCode])) {
                $dtrIds[$dtrCode] = Dtr::query()->create([
                    'feeder_id' => $feederIds[$feederCode],
                    'code' => $dtrCode,
                    'name' => $dtrName ?? $dtrCode,
                    'capacity_kva' => null,
                    'is_active' => true,
                ])->id;
            }

            $consumerBuffer[] = [
                'dtr_id' => $dtrIds[$dtrCode],
                'feeder_id' => $feederIds[$feederCode],
                'pole_id' => null,
                'name' => $this->str($row['Consumer Name'] ?? null),
                'phone' => $this->str($row['Mobile Number'] ?? null),
                'ivrs' => $this->str($row['IVRS Number'] ?? null),
                'account_no' => null,
                'msn' => $this->str($row['New Meter Serial Number'] ?? null),
                'address' => null,
                'phase' => $this->str($row['New Meter Type'] ?? null),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($consumerBuffer) >= 200) {
                $flush();
            }
            $bar->advance();
        }
        $flush();
        $bar->finish();
        $this->newLine();

        return [
            'regions' => Region::query()->count(),
            'circles' => Circle::query()->count(),
            'divisions' => Division::query()->count(),
            'zones' => Zone::query()->count(),
            'substations' => Substation::query()->count(),
            'feeders' => Feeder::query()->count(),
            'dtrs' => Dtr::query()->count(),
            'consumers' => Consumer::query()->count(),
        ];
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
        // Plain password — User model casts password with 'hashed'.
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
