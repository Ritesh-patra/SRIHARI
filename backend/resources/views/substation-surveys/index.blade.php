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
            if (!confirm('Permanently delete ' + this.selected.length + ' substation survey(s)? This cannot be undone.')) return;
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
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Substation Surveys</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">Filter → <strong class="text-white">View</strong> to load rows, then open a survey to review photos and approve. Download for Excel. Approved GPS is copied to the substation master for the network map.</p>
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <form method="GET" action="{{ route('substation-surveys.index') }}" class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
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
                    <select name="zone_id" class="al-input" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach($zones as $z)
                            <option value="{{ $z->id }}" @selected((string)$filters['zone_id'] === (string)$z->id)>{{ $z->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Substation</label>
                    <select name="substation_id" class="al-input">
                        <option value="">All</option>
                        @foreach($substations as $s)
                            <option value="{{ $s->id }}" @selected((string)$filters['substation_id'] === (string)$s->id)>{{ $s->name }}</option>
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
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Substation Code / Name</label>
                    <input type="text" name="substation_code" class="al-input" value="{{ $filters['substation_code'] }}" placeholder="Code or name">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Status</label>
                    <select name="status" class="al-input">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                        <option value="pending_approval" @selected($filters['status'] === 'pending_approval')>Pending Approval</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    </select>
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
                        Delete (<span x-text="selected.length"></span>)
                    </button>
                    <span class="text-xs text-muted" x-show="selected.length" x-cloak>Selected: <span x-text="selected.length"></span></span>
                </div>

                <form x-ref="bulkForm" method="POST" action="{{ route('substation-surveys.bulk-delete') }}" class="hidden">
                    @csrf
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-seas-950 text-white">
                            <tr>
                                <th class="px-3 py-2 w-10"><input type="checkbox" class="h-4 w-4 accent-rose-600" @change="toggleAll($event)" title="Select all"></th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Substation</th>
                                <th class="px-3 py-2">Zone</th>
                                <th class="px-3 py-2">Surveyor</th>
                                <th class="px-3 py-2">Meter No</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">GPS</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--al-border)">
                            @forelse($surveys as $row)
                                <tr class="hover:bg-black/[0.02]">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="row-check h-4 w-4 accent-rose-600" value="{{ $row->id }}" @change="sync()">
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-muted">{{ ($row->surveyed_at ?? $row->created_at)?->format('d M Y, H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-theme">{{ $row->substation_name ?: ($row->substation?->name ?? '—') }}</div>
                                        <div class="text-muted">{{ $row->substation_code }} · {{ $row->substation_type ?: '—' }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ $row->zone?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 font-semibold text-theme">{{ $row->surveyor?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $row->meter_number ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase
                                            {{ $row->status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : ($row->status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300') }}">
                                            {{ $row->display_status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if($row->latitude && $row->longitude)
                                            <a href="https://www.openstreetmap.org/?mlat={{ $row->latitude }}&mlon={{ $row->longitude }}#map=18/{{ $row->latitude }}/{{ $row->longitude }}"
                                               target="_blank" rel="noopener"
                                               class="font-bold text-volt hover:underline">{{ $row->latitude }} / {{ $row->longitude }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <a href="{{ route('substation-surveys.show', $row) }}" class="font-bold text-volt hover:underline">View →</a>
                                        <form method="POST" action="{{ route('substation-surveys.bulk-delete') }}" class="inline ml-2"
                                              onsubmit="return confirm('Permanently delete this substation survey?');">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $row->id }}">
                                            <button type="submit" class="text-xs font-bold text-rose-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-10 text-center text-muted">No substation surveys match these filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($surveys instanceof \Illuminate\Contracts\Pagination\Paginator && $surveys->hasPages())
                    <div class="border-t px-4 py-3" style="border-color: var(--al-border)">{{ $surveys->links() }}</div>
                @endif
            </section>
        @else
            <section class="al-panel px-5 py-10 text-center text-sm text-muted">
                Set filters and click <strong class="text-theme">View</strong> to load surveys.
                After View, use <strong class="text-theme">Select all</strong> + <strong class="text-rose-600">Delete</strong> (or per-row Delete).
            </section>
        @endif
    </div>
</x-app-layout>
