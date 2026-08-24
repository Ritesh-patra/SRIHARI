<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6" x-data="{
        selected: [],
        toggleAll(e) {
            const boxes = [...document.querySelectorAll('input.row-check')];
            this.selected = e.target.checked ? boxes.map(b => b.value) : [];
            boxes.forEach(b => b.checked = e.target.checked);
        },
        sync() {
            this.selected = [...document.querySelectorAll('input.row-check:checked')].map(b => b.value);
        },
        submitDelete() {
            if (this.selected.length === 0) return;
            if (!confirm('Permanently delete ' + this.selected.length + ' selected pole(s) and their consumer surveys? This cannot be undone.')) return;
            [...this.$refs.bulkForm.querySelectorAll('input[name=&quot;ids[]&quot;]')].forEach(n => n.remove());
            this.selected.forEach(id => {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = 'ids[]'; i.value = id;
                this.$refs.bulkForm.appendChild(i);
            });
            this.$refs.bulkForm.submit();
        }
    }">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10">
                <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">Operations</span>
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Pole Surveys</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">Filter and browse field poles. Use View to load results, Download for Excel. Delete removes consumer surveys on selected poles — not the pole master.</p>
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <form method="GET" action="{{ route('pole-surveys.index') }}" class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Region</label>
                    <select name="region_id" class="al-input" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}" @selected((string)$filters['region_id'] === (string)$r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Circle</label>
                    <select name="circle_id" class="al-input">
                        <option value="">All</option>
                        @foreach($circles as $c)
                            <option value="{{ $c->id }}" @selected((string)$filters['circle_id'] === (string)$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Division</label>
                    <select name="division_id" class="al-input">
                        <option value="">All</option>
                        @foreach($divisions as $d)
                            <option value="{{ $d->id }}" @selected((string)$filters['division_id'] === (string)$d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Zone / DC</label>
                    <select name="zone_id" class="al-input">
                        <option value="">All</option>
                        @foreach($zones as $z)
                            <option value="{{ $z->id }}" @selected((string)$filters['zone_id'] === (string)$z->id)>{{ $z->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">From Date</label>
                    <input type="date" name="from" class="al-input" value="{{ $filters['from'] }}">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">To Date</label>
                    <input type="date" name="to" class="al-input" value="{{ $filters['to'] }}">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Pole Code</label>
                    <input type="text" name="pole_code" class="al-input" value="{{ $filters['pole_code'] }}" placeholder="Pole no">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Status</label>
                    <select name="status" class="al-input">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="surveyed" @selected($filters['status'] === 'surveyed')>With consumer surveys</option>
                        <option value="not_surveyed" @selected($filters['status'] === 'not_surveyed')>No consumer surveys</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">DTR Code</label>
                    <input type="text" name="dtr_code" class="al-input" value="{{ $filters['dtr_code'] }}" placeholder="DTR code">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Surveyor</label>
                    <select name="surveyor_id" class="al-input">
                        <option value="">All</option>
                        @foreach($surveyors as $s)
                            <option value="{{ $s->id }}" @selected((string)$filters['surveyor_id'] === (string)$s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2 md:col-span-3 xl:col-span-4">
                    <button name="view" value="1" class="al-btn al-btn-primary">View</button>
                    <button name="download" value="1" class="al-btn al-btn-ink">Download</button>
                </div>
            </form>
        </section>

        @if($viewed)
            <section class="al-panel overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3" style="border-color: var(--al-border)">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-theme">
                        <input type="checkbox"
                               class="h-4 w-4 shrink-0 accent-rose-600"
                               title="Select all"
                               @change="toggleAll($event)">
                        Select all
                    </label>
                    <button type="button"
                        class="al-btn !bg-rose-600 !py-2 text-xs text-white ring-1 ring-rose-500/40 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="selected.length === 0"
                        @click="submitDelete()">
                        Delete poles (<span x-text="selected.length"></span>)
                    </button>
                    <span class="text-xs text-muted" x-show="selected.length" x-cloak>Selected poles: <span x-text="selected.length"></span></span>
                </div>

                <form x-ref="bulkForm" method="POST" action="{{ route('pole-surveys.bulk-delete') }}" class="hidden">
                    @csrf
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-seas-950 text-white">
                            <tr>
                                <th class="px-3 py-2 w-10"><input type="checkbox" class="h-4 w-4 accent-rose-600" @change="toggleAll($event)" title="Select all"></th>
                                <th class="px-3 py-2">Pole</th>
                                <th class="px-3 py-2">Source</th>
                                <th class="px-3 py-2">DTR</th>
                                <th class="px-3 py-2">Feeder</th>
                                <th class="px-3 py-2">Houses</th>
                                <th class="px-3 py-2">Surveys</th>
                                <th class="px-3 py-2">Zone</th>
                                <th class="px-3 py-2">Division</th>
                                <th class="px-3 py-2">Circle</th>
                                <th class="px-3 py-2">Lat / Lng</th>
                                <th class="px-3 py-2">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--al-border)">
                            @forelse($poles as $row)
                                @php
                                    $dtr = $row->dtr;
                                    $feeder = $dtr?->feeder;
                                    $sub = $feeder?->substation;
                                    $zone = $sub?->zone;
                                    $division = $zone?->division;
                                    $circle = $division?->circle;
                                @endphp
                                <tr class="hover:bg-black/[0.02]">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="row-check h-4 w-4 accent-rose-600" value="{{ $row->id }}" @change="sync()">
                                    </td>
                                    <td class="px-3 py-2 font-semibold text-volt">{{ $row->pole_no }}</td>
                                    <td class="px-3 py-2">{{ $row->source_type === 'previous_pole' ? 'Previous pole' : 'DTR' }}</td>
                                    <td class="px-3 py-2">{{ $dtr?->name ?? '—' }} <span class="text-muted">({{ $dtr?->code }})</span></td>
                                    <td class="px-3 py-2">{{ $feeder?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 font-semibold text-theme">{{ $row->houses_connected }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase
                                            {{ ($row->consumer_surveys_count ?? 0) > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">
                                            {{ $row->consumer_surveys_count ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ $zone?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $division?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $circle?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $row->latitude }} / {{ $row->longitude }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-muted">{{ $row->created_at?->format('d M Y, H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-5 py-10 text-center text-muted">No poles match these filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($poles instanceof \Illuminate\Contracts\Pagination\Paginator && $poles->hasPages())
                    <div class="border-t px-4 py-3" style="border-color: var(--al-border)">{{ $poles->links() }}</div>
                @endif
            </section>
        @else
            <section class="al-panel px-5 py-10 text-center text-sm text-muted">
                Set filters and click <strong class="text-theme">View</strong> to load poles.
            </section>
        @endif
    </div>
</x-app-layout>
