<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Network masters</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">Master Data</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.masters.dtrs.index') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">DTR list</a>
                <a href="{{ route('admin.masters.consumers.index') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">Consumers</a>
                <a href="{{ route('admin.masters.import') }}" class="seas-btn-primary !py-2 !px-3 text-xs">CSV Import</a>
                <a href="{{ route('admin.hierarchy') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">Hierarchy Tree</a>
            </div>
        </div>
    </x-slot>

    @php
        $statMeta = [
            'regions' => ['Region', 'M3 7h18M3 12h18M3 17h12'],
            'circles' => ['Circle', 'M12 3a9 9 0 1 0 0 18'],
            'divisions' => ['Division', 'M4 6h16M4 12h10M4 18h14'],
            'zones' => ['Zone', 'M12 2l3 7h7l-5.5 4.5L18 22l-6-4-6 4 1.5-8.5L2 9h7z'],
            'substations' => ['Substation', 'M4 20V10l8-6 8 6v10H4z'],
            'feeders' => ['Feeder', 'M13 2 4 14h7l-1 8 10-14h-7l1-6'],
            'dtrs' => ['DTR', 'M12 3v4M8 9l-2 10h12L16 9M9 9h6'],
            'consumers' => ['Consumer', 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M12 3a4 4 0 1 0 0 8'],
        ];
        $displayStats = collect($stats)->only(array_keys($statMeta));
    @endphp

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-6 sm:space-y-6 lg:space-y-8 animate-fade-up">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="seas-stagger grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach($displayStats as $label => $count)
                @php $m = $statMeta[$label] ?? [ucfirst($label), 'M4 12h16']; @endphp
                @php
                    $href = match($label) {
                        'dtrs' => route('admin.masters.dtrs.index'),
                        'consumers' => route('admin.masters.consumers.index'),
                        default => null,
                    };
                @endphp
                <a href="{{ $href ?? '#' }}" @if(!$href) onclick="return false" @endif class="seas-card p-4 hover:-translate-y-1 {{ $href ? '' : 'pointer-events-none' }}">
                    <div class="flex items-center justify-between">
                        <span class="seas-icon bg-seas-950 text-white !h-9 !w-9">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m[1] }}"/></svg>
                        </span>
                        <span class="text-[10px] font-extrabold uppercase tracking-wide text-seas-400">{{ $m[0] }}</span>
                    </div>
                    <div class="mt-3 font-display text-3xl font-extrabold text-seas-900">{{ number_format($count) }}</div>
                    @if($label === 'consumers')
                        <div class="mt-1 text-[10px] text-seas-400">MI {{ number_format($stats['consumers_mi'] ?? 0) }} · Master {{ number_format($stats['consumers_master'] ?? 0) }}</div>
                    @endif
                </a>
            @endforeach
        </section>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.masters.export', 'feeders') }}" class="seas-btn-secondary !py-2 text-xs">Export Feeders</a>
            <a href="{{ route('admin.masters.export', 'dtrs') }}" class="seas-btn-secondary !py-2 text-xs">Export DTRs</a>
            <a href="{{ route('admin.masters.export', 'consumers') }}" class="seas-btn-secondary !py-2 text-xs">Export Consumers</a>
        </div>

        <div class="al-panel px-4 py-3 text-xs text-muted">
            <strong class="text-theme">Note:</strong> Large DTR / Consumer lists are paginated (searchable). Poles are field-only.
        </div>

        <section class="grid gap-4 lg:grid-cols-2">
            @php
                $forms = [
                    ['admin.masters.regions', 'Add Region', 'R', null, [['name','text','Region name']]],
                    ['admin.masters.circles', 'Add Circle', 'C', ['region_id', $regions, 'name'], [['name','text','Circle name']]],
                    ['admin.masters.divisions', 'Add Division', 'D', ['circle_id', $circles, 'name'], [['name','text','Division name']]],
                    ['admin.masters.zones', 'Add Zone', 'Z', ['division_id', $divisions, 'name'], [['name','text','Zone name']]],
                    ['admin.masters.substations', 'Add Substation', 'S', ['zone_id', $zones, 'name'], [['name','text','Substation name']]],
                ];
            @endphp

            @foreach($forms as [$action, $title, $abbr, $select, $fields])
                <form method="POST" action="{{ route($action) }}" class="seas-card overflow-hidden hover:-translate-y-0.5">
                    @csrf
                    <div class="flex items-center gap-3 border-b border-seas-100 bg-canvas-soft/80 px-5 py-3.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-seas-950 text-xs font-extrabold text-white">{{ $abbr }}</span>
                        <h3 class="font-display font-extrabold text-seas-900">{{ $title }}</h3>
                    </div>
                    <div class="space-y-3 p-5">
                        @if($select)
                            <select name="{{ $select[0] }}" required class="seas-input">
                                @foreach($select[1] as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->{$select[2]} }}</option>
                                @endforeach
                            </select>
                        @endif
                        @foreach($fields as [$name, $type, $ph])
                            <input name="{{ $name }}" type="{{ $type }}" required class="seas-input" placeholder="{{ $ph }}">
                        @endforeach
                        <button class="seas-btn-primary w-full !py-2.5 text-xs">Save</button>
                    </div>
                </form>
            @endforeach

            <form method="POST" action="{{ route('admin.masters.feeders') }}" class="seas-card overflow-hidden hover:-translate-y-0.5">
                @csrf
                <div class="flex items-center gap-3 border-b border-seas-100 bg-canvas-soft/80 px-5 py-3.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-volt text-xs font-extrabold text-white">F</span>
                    <h3 class="font-display font-extrabold text-seas-900">Add Feeder</h3>
                </div>
                <div class="space-y-3 p-5">
                    <select name="substation_id" required class="seas-input">
                        @foreach($substations as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <input name="code" required class="seas-input" placeholder="Feeder code">
                    <input name="name" required class="seas-input" placeholder="Feeder name">
                    <button class="seas-btn-primary w-full !py-2.5 text-xs">Save Feeder</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.masters.dtrs') }}" class="seas-card overflow-hidden hover:-translate-y-0.5">
                @csrf
                <div class="flex items-center gap-3 border-b border-seas-100 bg-canvas-soft/80 px-5 py-3.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-volt text-xs font-extrabold text-white">DTR</span>
                    <h3 class="font-display font-extrabold text-seas-900">Add DTR</h3>
                </div>
                <div class="space-y-3 p-5">
                    <input name="feeder_code" class="seas-input" placeholder="Feeder code (preferred)" list="feeder-code-hint">
                    <p class="text-[10px] text-seas-400">Or pick from <a class="underline" href="{{ route('admin.masters.dtrs.index') }}">DTR list</a> after creating under a known feeder.</p>
                    <input name="code" required class="seas-input" placeholder="DTR code">
                    <input name="name" required class="seas-input" placeholder="DTR name">
                    <input name="capacity_kva" type="number" class="seas-input" placeholder="Capacity kVA">
                    <input type="hidden" name="feeder_id" id="feeder_id_hidden" value="">
                    <button class="seas-btn-primary w-full !py-2.5 text-xs">Save DTR</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.masters.consumers') }}" class="seas-card overflow-hidden hover:-translate-y-0.5 lg:col-span-2">
                @csrf
                <div class="flex items-center gap-3 border-b border-seas-100 bg-seas-950 px-5 py-3.5 text-white">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-volt text-xs font-extrabold">C</span>
                    <div>
                        <h3 class="font-display font-extrabold">Add Master Consumer</h3>
                        <p class="text-[11px] text-white/45">Use DTR code — no full DTR dump in this form</p>
                    </div>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <input name="dtr_code" required class="seas-input sm:col-span-2 lg:col-span-1" placeholder="DTR code">
                    <input name="name" class="seas-input" placeholder="Consumer name">
                    <input name="phone" class="seas-input" placeholder="Phone">
                    <input name="ivrs" class="seas-input" placeholder="IVRS">
                    <input name="msn" class="seas-input" placeholder="MSN">
                    <button class="seas-btn-primary sm:col-span-2 lg:col-span-1">Save Consumer</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
