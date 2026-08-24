<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Circle;
use App\Models\Consumer;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\Region;
use App\Models\Substation;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MasterController extends Controller
{
    public function index()
    {
        $stats = [
            'regions' => Region::count(),
            'circles' => Circle::count(),
            'divisions' => Division::count(),
            'zones' => Zone::count(),
            'substations' => Substation::count(),
            'feeders' => Feeder::count(),
            'dtrs' => Dtr::count(),
            'consumers' => Consumer::count(),
            'consumers_mi' => Schema::hasColumn('consumers', 'source')
                ? Consumer::where('source', 'mi')->count()
                : Consumer::count(),
            'consumers_master' => Schema::hasColumn('consumers', 'source')
                ? Consumer::where('source', 'master')->count()
                : 0,
        ];

        // Lightweight parent options only (no 160k DTR dump into HTML).
        $regions = Region::orderBy('name')->get(['id', 'name']);
        $circles = Circle::orderBy('name')->get(['id', 'region_id', 'name']);
        $divisions = Division::orderBy('name')->get(['id', 'circle_id', 'name']);
        $zones = Zone::orderBy('name')->limit(500)->get(['id', 'division_id', 'name']);
        $substations = Substation::orderBy('name')->limit(500)->get(['id', 'zone_id', 'name']);

        return view('admin.masters.index', compact('stats', 'regions', 'circles', 'divisions', 'zones', 'substations'));
    }

    public function dtrs(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $query = Dtr::query()->with(['feeder:id,code,name'])->orderBy('code');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhereHas('feeder', fn ($f) => $f->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            });
        }

        $dtrs = $query->paginate(50)->withQueryString();

        return view('admin.masters.dtrs', compact('dtrs', 'q'));
    }

    public function consumers(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $source = $request->get('source');
        $query = Consumer::query()
            ->with(['dtr:id,code,name', 'feeder:id,code,name'])
            ->orderByDesc('id');

        if (in_array($source, ['mi', 'master'], true)) {
            $query->where('source', $source);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('ivrs', 'like', "%{$q}%")
                    ->orWhere('msn', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('account_no', 'like', "%{$q}%");
            });
        }

        $consumers = $query->paginate(50)->withQueryString();
        $sourceCounts = [
            'all' => Consumer::count(),
            'mi' => Consumer::where('source', 'mi')->count(),
            'master' => Consumer::where('source', 'master')->count(),
        ];

        return view('admin.masters.consumers', compact('consumers', 'q', 'source', 'sourceCounts'));
    }

    public function storeRegion(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $region = Region::create($data);
        ActivityLog::record('master.region_created', $region);

        return back()->with('success', 'Region added.');
    }

    public function storeCircle(Request $request)
    {
        $data = $request->validate([
            'region_id' => ['required', 'exists:regions,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $circle = Circle::create($data);
        ActivityLog::record('master.circle_created', $circle);

        return back()->with('success', 'Circle added.');
    }

    public function storeDivision(Request $request)
    {
        $data = $request->validate([
            'circle_id' => ['required', 'exists:circles,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        Division::create($data);

        return back()->with('success', 'Division added.');
    }

    public function storeZone(Request $request)
    {
        $data = $request->validate([
            'division_id' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        Zone::create($data);

        return back()->with('success', 'Zone added.');
    }

    public function storeSubstation(Request $request)
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:zones,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        Substation::create($data);

        return back()->with('success', 'Substation added.');
    }

    public function storeFeeder(Request $request)
    {
        $data = $request->validate([
            'substation_id' => ['required', 'exists:substations,id'],
            'code' => ['required', 'string', 'max:50', 'unique:feeders,code'],
            'name' => ['required', 'string', 'max:190'],
        ]);
        Feeder::create($data);

        return back()->with('success', 'Feeder added.');
    }

    public function storeDtr(Request $request)
    {
        $data = $request->validate([
            'feeder_id' => ['nullable', 'exists:feeders,id'],
            'feeder_code' => ['nullable', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:50', 'unique:dtrs,code'],
            'name' => ['required', 'string', 'max:190'],
            'capacity_kva' => ['nullable', 'integer', 'min:1'],
        ]);

        $feeder = null;
        if (! empty($data['feeder_id'])) {
            $feeder = Feeder::find($data['feeder_id']);
        } elseif (! empty($data['feeder_code'])) {
            $feeder = Feeder::where('code', $data['feeder_code'])->first();
        }
        if (! $feeder) {
            return back()->withErrors(['feeder_code' => 'Enter a valid feeder code.'])->withInput();
        }

        Dtr::create([
            'feeder_id' => $feeder->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'capacity_kva' => $data['capacity_kva'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'DTR added.');
    }

    public function storeConsumer(Request $request)
    {
        $data = $request->validate([
            'dtr_id' => ['nullable', 'exists:dtrs,id'],
            'dtr_code' => ['nullable', 'string', 'max:50'],
            'feeder_id' => ['nullable', 'exists:feeders,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'ivrs' => ['nullable', 'string', 'max:50'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'msn' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'phase' => ['nullable', 'string', 'max:30'],
        ]);

        $dtr = null;
        if (! empty($data['dtr_id'])) {
            $dtr = Dtr::findOrFail($data['dtr_id']);
        } elseif (! empty($data['dtr_code'])) {
            $dtr = Dtr::where('code', $data['dtr_code'])->first();
        }
        if (! $dtr) {
            return back()->withErrors(['dtr_code' => 'Enter a valid DTR code (search in DTR list).'])->withInput();
        }

        unset($data['dtr_code']);
        $data['dtr_id'] = $dtr->id;
        $data['feeder_id'] = $data['feeder_id'] ?? $dtr->feeder_id;
        $data['pole_id'] = null;
        $data['source'] = $data['source'] ?? 'master';
        Consumer::create($data);

        return back()->with('success', 'Master consumer added (no pole — assigned in field survey).');
    }

    public function importForm()
    {
        return view('admin.masters.import');
    }

    /** CSV import: type=feeders|dtrs|consumers */
    public function import(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['feeders', 'dtrs', 'consumers'])],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map('strtolower', array_map('trim', fgetcsv($handle) ?: []));
        $ok = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }
                $map = [];
                foreach ($header as $i => $col) {
                    $map[$col] = $row[$i] ?? null;
                }

                try {
                    match ($data['type']) {
                        'feeders' => $this->importFeederRow($map),
                        'dtrs' => $this->importDtrRow($map),
                        'consumers' => $this->importConsumerRow($map),
                    };
                    $ok++;
                } catch (\Throwable $e) {
                    $errors[] = "Row {$rowNum}: ".$e->getMessage();
                    if (count($errors) > 50) {
                        break;
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            fclose($handle);
        }

        ActivityLog::record('master.import', null, ['type' => $data['type'], 'ok' => $ok, 'errors' => count($errors)]);

        return back()->with('success', "Imported {$ok} rows.")->with('import_errors', $errors);
    }

    public function export(string $type)
    {
        $filename = $type.'_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($type) {
            $out = fopen('php://output', 'w');
            match ($type) {
                'feeders' => $this->exportFeeders($out),
                'dtrs' => $this->exportDtrs($out),
                'consumers' => $this->exportConsumers($out),
                default => fputcsv($out, ['error']),
            };
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function importFeederRow(array $m): void
    {
        $substation = Substation::where('name', $m['substation'] ?? '')->first()
            ?? Substation::find($m['substation_id'] ?? 0);
        if (! $substation) {
            throw new \RuntimeException('Substation not found');
        }
        Feeder::updateOrCreate(
            ['code' => $m['code']],
            ['substation_id' => $substation->id, 'name' => $m['name'] ?? $m['code'], 'is_active' => true]
        );
    }

    private function importDtrRow(array $m): void
    {
        $feeder = Feeder::where('code', $m['feeder_code'] ?? '')->first()
            ?? Feeder::find($m['feeder_id'] ?? 0);
        if (! $feeder) {
            throw new \RuntimeException('Feeder not found');
        }
        Dtr::updateOrCreate(
            ['code' => $m['code']],
            [
                'feeder_id' => $feeder->id,
                'name' => $m['name'] ?? $m['code'],
                'capacity_kva' => $m['capacity_kva'] ?? null,
                'is_active' => true,
            ]
        );
    }

    private function importConsumerRow(array $m): void
    {
        $dtr = Dtr::where('code', $m['dtr_code'] ?? '')->first()
            ?? Dtr::find($m['dtr_id'] ?? 0);
        if (! $dtr) {
            throw new \RuntimeException('DTR not found');
        }
        Consumer::create([
            'dtr_id' => $dtr->id,
            'feeder_id' => $dtr->feeder_id,
            'pole_id' => null,
            'name' => $m['name'] ?? null,
            'phone' => $m['phone'] ?? null,
            'ivrs' => $m['ivrs'] ?? null,
            'account_no' => $m['account_no'] ?? null,
            'msn' => $m['msn'] ?? null,
            'address' => $m['address'] ?? null,
            'phase' => $m['phase'] ?? null,
            'is_active' => true,
            'source' => $m['source'] ?? 'master',
        ]);
    }

    private function exportFeeders($out): void
    {
        fputcsv($out, ['code', 'name', 'substation', 'substation_id']);
        Feeder::with('substation')->orderBy('code')->chunk(200, function ($rows) use ($out) {
            foreach ($rows as $f) {
                fputcsv($out, [$f->code, $f->name, $f->substation?->name, $f->substation_id]);
            }
        });
    }

    private function exportDtrs($out): void
    {
        fputcsv($out, ['code', 'name', 'capacity_kva', 'feeder_code', 'feeder_id']);
        Dtr::with('feeder')->orderBy('code')->chunk(200, function ($rows) use ($out) {
            foreach ($rows as $d) {
                fputcsv($out, [$d->code, $d->name, $d->capacity_kva, $d->feeder?->code, $d->feeder_id]);
            }
        });
    }

    private function exportConsumers($out): void
    {
        fputcsv($out, ['name', 'phone', 'ivrs', 'account_no', 'msn', 'dtr_code', 'phase', 'address']);
        Consumer::with('dtr')->orderBy('id')->chunk(200, function ($rows) use ($out) {
            foreach ($rows as $c) {
                fputcsv($out, [$c->name, $c->phone, $c->ivrs, $c->account_no, $c->msn, $c->dtr?->code, $c->phase, $c->address]);
            }
        });
    }
}
