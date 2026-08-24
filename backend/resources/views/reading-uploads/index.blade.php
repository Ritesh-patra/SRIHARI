@php
    $maxBytes = $maxTotalMb * 1024 * 1024;
    $acceptAttr = implode(',', array_map(fn ($e) => '.'.$e, $allowedExtensions));
    $hints = [
        'feeder' => 'Feeder Code · Feeder Name · Reading Date · KWH / Units · KVAH · MD (KW)',
        'dtr' => 'DTR Code · DTR Name · Feeder Code · Reading Date · KWH / Units · KVAH · MD (KW)',
        'consumer' => 'IVRS · MSN / Meter Serial · Account No · DTR Code · Feeder Code · Reading Date · KWH / Units',
    ];
@endphp
<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6"
         x-data="readingUploads({ tab: '{{ $type }}', busyIds: @js($busyIds), statusUrl: '{{ route('reading-uploads.status') }}' })">

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
                <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">Overview</span>
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Reading Upload</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">
                    Upload Feeder, DTR and Consumer consumption files. Uploads are sliced into
                    {{ number_format($chunkSize / 1048576, 0) }} MB chunks and parsed in the background,
                    so files up to {{ number_format($maxTotalMb) }} MB work on shared hosting.
                </p>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            @foreach($labels as $key => $label)
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-bold transition"
                        :class="tab === '{{ $key }}' ? 'border-[#E10600] bg-[#E10600] text-white' : 'border-[var(--al-border)] bg-[var(--al-surface-2)] text-theme'"
                        @click="setTab('{{ $key }}')">
                    {{ $label }}
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                          :class="tab === '{{ $key }}' ? 'bg-white/20' : 'bg-black/10 dark:bg-white/10'">{{ (int) ($counts[$key] ?? 0) }}</span>
                </button>
            @endforeach
        </div>

        @foreach($labels as $key => $label)
            <div x-show="tab === '{{ $key }}'" x-cloak class="space-y-5">

                <section class="al-panel p-5 sm:p-6"
                         x-data="chunkedUpload({
                            initUrl: '{{ route('uploads.chunk.init') }}',
                            partUrl: '{{ route('uploads.chunk.part', ['uuid' => '__UUID__']) }}',
                            completeUrl: '{{ route('uploads.chunk.complete', ['uuid' => '__UUID__']) }}',
                            statusUrl: '{{ route('uploads.chunk.status', ['uuid' => '__UUID__']) }}',
                            abortUrl: '{{ route('uploads.chunk.abort', ['uuid' => '__UUID__']) }}',
                            purpose: 'reading',
                            chunkSize: {{ $chunkSize }},
                            maxBytes: {{ $maxBytes }},
                            accept: @js($allowedExtensions),
                            csrf: '{{ csrf_token() }}',
                            autoStart: false,
                            meta: function () {
                                return {
                                    type: '{{ $key }}',
                                    period_from: this.$refs.periodFrom?.value || null,
                                    period_to: this.$refs.periodTo?.value || null,
                                    period_label: this.$refs.periodLabel?.value || null,
                                };
                            }
                         })">
                    <h2 class="al-display text-lg font-bold text-theme">Upload {{ $label }}</h2>
                    <p class="mt-1 text-sm text-muted">Recognised columns: {{ $hints[$key] ?? '' }}. Anything else is kept in the raw data column.</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Period from</label>
                            <input type="date" x-ref="periodFrom" :disabled="busy"
                                   class="w-full rounded-xl border px-3 py-2 text-sm text-theme"
                                   style="border-color: var(--al-border); background: var(--al-surface-2)">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Period to</label>
                            <input type="date" x-ref="periodTo" :disabled="busy"
                                   class="w-full rounded-xl border px-3 py-2 text-sm text-theme"
                                   style="border-color: var(--al-border); background: var(--al-surface-2)">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Period label</label>
                            <input type="text" x-ref="periodLabel" maxlength="64" placeholder="Aug-2026" :disabled="busy"
                                   class="w-full rounded-xl border px-3 py-2 text-sm text-theme"
                                   style="border-color: var(--al-border); background: var(--al-surface-2)">
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <label class="al-btn al-btn-ghost !py-2 !px-3 text-xs" :class="busy ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                            <span x-text="fileName || 'Choose file'">Choose file</span>
                            <input type="file" accept="{{ $acceptAttr }}" class="sr-only" :disabled="busy" @change="pick($event)">
                        </label>
                        <button type="button" class="al-btn al-btn-primary !py-2 !px-4 text-xs disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!file || busy" @click="start()">
                            Start upload
                        </button>
                        <button type="button" class="al-btn al-btn-ghost !py-2 !px-3 text-xs" x-show="busy" x-cloak @click="cancel()">Cancel</button>
                        <span class="text-[10px] text-muted">
                            {{ implode(' · ', array_map(fn ($e) => '.'.$e, $allowedExtensions)) }} · up to {{ number_format($maxTotalMb) }} MB
                        </span>
                    </div>

                    <template x-if="phase !== 'idle'">
                        <div class="mt-4 space-y-1.5">
                            <div class="h-2 w-full overflow-hidden rounded-full" style="background: var(--al-surface-2)">
                                <div class="h-full rounded-full transition-all duration-200"
                                     :class="phase === 'error' ? 'bg-rose-500' : (phase === 'done' ? 'bg-emerald-500' : 'bg-[#E10600]')"
                                     :style="`width: ${percent}%`"></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                <span class="font-bold text-theme" x-text="`${percent}%`"></span>
                                <span x-text="`${uploadedLabel} / ${totalLabel}`"></span>
                                <span x-show="phase === 'uploading'" x-text="speedLabel"></span>
                                <span x-show="phase === 'uploading' && etaLabel !== '—'" x-text="`ETA ${etaLabel}`"></span>
                                <span class="font-semibold" x-text="phaseLabel"></span>
                            </div>
                            <div class="text-[11px] text-muted" x-show="message" x-text="message"></div>
                            <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400" x-show="error" x-text="error"></div>
                        </div>
                    </template>
                </section>

                <section class="al-panel overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--al-border)">
                        <div class="al-display font-bold text-theme">
                            {{ $label }} history
                            <span class="ml-1 text-[11px] font-medium text-muted">latest {{ $historyLimit }}</span>
                        </div>
                        <a href="{{ route('reading-uploads.export', ['type' => $key]) }}" class="al-btn al-btn-ghost !py-2 !px-3 text-xs">Download Excel</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-muted" style="background: var(--al-surface-2)">
                                    <th class="px-5 py-3">Date</th>
                                    <th class="px-5 py-3">File</th>
                                    <th class="px-5 py-3">Size</th>
                                    <th class="px-5 py-3">Period</th>
                                    <th class="px-5 py-3">Rows</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" style="border-color: var(--al-border)">
                                @forelse($histories[$key] as $row)
                                    <tr style="border-color: var(--al-border)">
                                        <td class="px-5 py-4 whitespace-nowrap text-muted">
                                            {{ $row->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                        </td>
                                        <td class="px-5 py-4">
                                            <a href="{{ route('reading-uploads.show', $row) }}" class="font-semibold text-theme hover:underline">{{ $row->original_name }}</a>
                                            <div class="text-[11px] text-muted">{{ $labels[$row->type] ?? $row->type }}</div>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap text-muted">
                                            {{ $row->size_bytes !== null ? number_format($row->size_bytes / 1048576, 1).' MB' : '—' }}
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap text-muted">
                                            @if($row->period_label)
                                                {{ $row->period_label }}
                                            @elseif($row->period_from || $row->period_to)
                                                {{ $row->period_from?->format('d M Y') ?? '…' }} – {{ $row->period_to?->format('d M Y') ?? '…' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap text-[11px]">
                                            <span x-show="!live[{{ $row->id }}]">
                                                <span class="text-theme">{{ number_format((int) $row->rows_imported) }}</span>
                                                <span class="text-muted">/ {{ number_format((int) $row->rows_total) }}</span>
                                                @if((int) $row->rows_failed > 0)
                                                    <span class="text-rose-600 dark:text-rose-400">· {{ number_format((int) $row->rows_failed) }} skipped</span>
                                                @endif
                                            </span>
                                            <span x-show="live[{{ $row->id }}]" x-cloak x-html="rowsCell({{ $row->id }})"></span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span x-show="!live[{{ $row->id }}]">
                                                @include('reading-uploads._status-badge', ['status' => $row->status])
                                            </span>
                                            <span x-show="live[{{ $row->id }}]" x-cloak x-html="statusBadge({{ $row->id }})"></span>
                                            @if($row->error)
                                                <div class="mt-1 max-w-xs text-[11px] text-rose-600 dark:text-rose-400" title="{{ $row->error }}">{{ \Illuminate\Support\Str::limit($row->error, 140) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                                @if($row->status === \App\Models\ReadingUpload::STATUS_FAILED)
                                                    <form method="POST" action="{{ route('reading-uploads.reprocess', $row) }}">
                                                        @csrf
                                                        <button type="submit" class="al-btn al-btn-ghost !py-1.5 !px-2.5 text-[11px]">Retry</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('reading-uploads.destroy', $row) }}"
                                                      onsubmit="return confirm('Delete {{ addslashes($row->original_name) }} and all its parsed rows?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="al-btn al-btn-ghost !py-1.5 !px-2.5 text-[11px] text-rose-600 dark:text-rose-400">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-10 text-center text-muted">No {{ strtolower($label) }} files uploaded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            function readingUploads(cfg) {
                return {
                    tab: cfg.tab || 'feeder',
                    live: {},
                    timer: null,

                    init() {
                        if ((cfg.busyIds || []).length) {
                            this.poll(cfg.busyIds);
                        }
                    },

                    setTab(tab) {
                        this.tab = tab;
                        const url = new URL(window.location.href);
                        url.searchParams.set('type', tab);
                        window.history.replaceState({}, '', url);
                    },

                    async poll(ids) {
                        const query = new URLSearchParams({ ids: ids.join(',') });
                        let ticks = 0;

                        const tick = async () => {
                            ticks++;
                            try {
                                const response = await fetch(`${cfg.statusUrl}?${query}`, {
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                });
                                const payload = await response.json();
                                let stillBusy = false;

                                (payload.uploads || []).forEach((row) => {
                                    this.live[row.id] = row;
                                    if (row.status === 'pending' || row.status === 'processing') {
                                        stillBusy = true;
                                    }
                                });

                                if (!stillBusy) {
                                    window.location.reload();
                                    return;
                                }
                            } catch (e) {
                                // transient — try again on the next tick
                            }

                            if (ticks < 900) {
                                this.timer = setTimeout(tick, 3000);
                            }
                        };

                        this.timer = setTimeout(tick, 3000);
                    },

                    rowsCell(id) {
                        const row = this.live[id];
                        if (!row) return null;
                        const imported = Number(row.rows_imported || 0).toLocaleString();
                        const total = Number(row.rows_total || 0).toLocaleString();
                        const failed = Number(row.rows_failed || 0);
                        const skipped = failed > 0 ? ` <span class="text-rose-600 dark:text-rose-400">· ${failed.toLocaleString()} skipped</span>` : '';
                        return `<span class="text-theme">${imported}</span> <span class="text-muted">/ ${total}</span>${skipped}`;
                    },

                    statusBadge(id) {
                        const row = this.live[id];
                        if (!row) return null;
                        const tone = {
                            pending: 'bg-zinc-500/15 text-zinc-600 dark:text-zinc-300',
                            processing: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
                            completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
                            failed: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
                        }[row.status] || 'bg-zinc-500/15 text-zinc-600';
                        const label = row.status.charAt(0).toUpperCase() + row.status.slice(1);
                        return `<span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-bold ${tone}">${label}</span>`;
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
