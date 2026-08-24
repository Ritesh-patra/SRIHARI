@php
    $maxBytes = $maxTotalMb * 1024 * 1024;
    $busy = $uploads->contains(fn ($u) => $u->isBusy());
@endphp
<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6" x-data="reportAnalysisCompare({
        compareCount: {{ (int) old('compare_count', $selection['compare_count'] ?? 2) }},
        initialSelected: @js(old('sources', $selection['sources'] ?? [])),
        uploaded: @js($uploads->keys()->values()->all()),
        busy: {{ $busy ? 'true' : 'false' }}
    })">
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
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Report Analysis</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">
                    Phase 1: upload CI / CMDM / DFIS / NGB / WFM sources, then pick how many to compare.
                    Files are uploaded in {{ number_format($chunkSize / 1048576, 0) }} MB chunks, so files up to {{ number_format($maxTotalMb) }} MB work even on shared hosting.
                </p>
            </div>
        </section>

        <section class="al-panel overflow-hidden">
            <div class="border-b px-5 py-4 al-display font-bold text-theme" style="border-color: var(--al-border)">
                Source uploads
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-muted" style="background: var(--al-surface-2)">
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Upload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--al-border)">
                        @foreach($labels as $key => $label)
                            @php $row = $uploads->get($key); @endphp
                            <tr style="border-color: var(--al-border)">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-theme">{{ $label }}</div>
                                    <div class="text-[11px] text-muted">{{ strtoupper($key) }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    @if($row)
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if($row->isBusy())
                                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500/15 px-2 py-1 text-[11px] font-bold text-amber-600 dark:text-amber-400">
                                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                                    {{ $row->parseStatus() === 'processing' ? 'Reading file' : 'Queued' }}
                                                </span>
                                            @elseif($row->parseStatus() === 'failed')
                                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-rose-500/15 px-2 py-1 text-[11px] font-bold text-rose-600 dark:text-rose-400">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                    Parse failed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/15 px-2 py-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                    Uploaded
                                                </span>
                                            @endif
                                        </div>
                                        <div class="mt-1.5 font-medium text-theme">{{ $row->original_name }}</div>
                                        <div class="mt-0.5 text-[11px] text-muted">
                                            @if($row->row_count !== null)
                                                {{ number_format($row->row_count) }} data row{{ $row->row_count === 1 ? '' : 's' }}
                                            @else
                                                Row count pending
                                            @endif
                                            @if($row->size_bytes)
                                                · {{ number_format($row->size_bytes / 1048576, 1) }} MB
                                            @endif
                                            · {{ $row->updated_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                        </div>
                                        @if($row->parse_error)
                                            <div class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $row->parse_error }}</div>
                                        @elseif($row->parse_note)
                                            <div class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">{{ $row->parse_note }}</div>
                                        @elseif(is_array($row->headers_json) && count($row->headers_json))
                                            <div class="mt-1 text-[11px] text-muted truncate max-w-md" title="{{ implode(', ', $row->headers_json) }}">
                                                Headers: {{ implode(', ', array_slice($row->headers_json, 0, 8)) }}{{ count($row->headers_json) > 8 ? '…' : '' }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-bold text-muted" style="background: var(--al-surface-2)">
                                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                                            Not uploaded
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div x-data="chunkedUpload({
                                            initUrl: '{{ route('uploads.chunk.init') }}',
                                            partUrl: '{{ route('uploads.chunk.part', ['uuid' => '__UUID__']) }}',
                                            completeUrl: '{{ route('uploads.chunk.complete', ['uuid' => '__UUID__']) }}',
                                            statusUrl: '{{ route('uploads.chunk.status', ['uuid' => '__UUID__']) }}',
                                            abortUrl: '{{ route('uploads.chunk.abort', ['uuid' => '__UUID__']) }}',
                                            purpose: 'report_analysis',
                                            meta: { source: '{{ $key }}' },
                                            chunkSize: {{ $chunkSize }},
                                            maxBytes: {{ $maxBytes }},
                                            accept: @js($allowedExtensions),
                                            csrf: '{{ csrf_token() }}'
                                        })" class="min-w-[16rem]">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <label class="al-btn al-btn-ghost !py-2 !px-3 text-xs"
                                                   :class="busy ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                                                <span x-text="busy ? 'Uploading…' : 'Choose file'">Choose file</span>
                                                <input type="file"
                                                       accept=".csv,.txt,.xlsx,.xls"
                                                       class="sr-only"
                                                       :disabled="busy"
                                                       @change="pick($event)">
                                            </label>
                                            <button type="button"
                                                    class="al-btn al-btn-ghost !py-2 !px-3 text-xs"
                                                    x-show="busy"
                                                    x-cloak
                                                    @click="cancel()">
                                                Cancel
                                            </button>
                                        </div>

                                        <template x-if="phase !== 'idle'">
                                            <div class="mt-2 space-y-1">
                                                <div class="h-1.5 w-full overflow-hidden rounded-full" style="background: var(--al-surface-2)">
                                                    <div class="h-full rounded-full transition-all duration-200"
                                                         :class="phase === 'error' ? 'bg-rose-500' : (phase === 'done' ? 'bg-emerald-500' : 'bg-[#E10600]')"
                                                         :style="`width: ${percent}%`"></div>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-muted">
                                                    <span class="font-bold text-theme" x-text="`${percent}%`"></span>
                                                    <span x-text="`${uploadedLabel} / ${totalLabel}`"></span>
                                                    <span x-show="phase === 'uploading'" x-text="speedLabel"></span>
                                                    <span x-show="phase === 'uploading' && etaLabel !== '—'" x-text="`ETA ${etaLabel}`"></span>
                                                    <span class="font-semibold" x-text="phaseLabel"></span>
                                                </div>
                                                <div class="text-[10px] text-muted" x-show="message" x-text="message"></div>
                                                <div class="text-[10px] font-semibold text-rose-600 dark:text-rose-400" x-show="error" x-text="error"></div>
                                            </div>
                                        </template>
                                    </div>

                                    <noscript>
                                        <form method="POST" action="{{ route('report-analysis.upload') }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="source" value="{{ $key }}">
                                            <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls" required class="text-xs">
                                            <button type="submit" class="al-btn al-btn-primary !py-2 !px-3 text-xs">Upload</button>
                                        </form>
                                    </noscript>

                                    <div class="mt-1 text-[10px] text-muted">
                                        {{ implode(' · ', array_map(fn ($e) => '.'.$e, $allowedExtensions)) }} · up to {{ number_format($maxTotalMb) }} MB
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <h2 class="al-display text-lg font-bold text-theme">Compare setup</h2>
            <p class="mt-1 text-sm text-muted">Choose how many sources to compare, then select exactly that many uploaded databases.</p>

            <form method="POST" action="{{ route('report-analysis.selection') }}" class="mt-5 space-y-5" @submit="if (!canContinue) { $event.preventDefault() }">
                @csrf

                <div>
                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted">Compare with</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach([2, 3, 4] as $n)
                            <button type="button"
                                    class="inline-flex min-w-[3.25rem] items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-bold transition"
                                    :class="compareCount === {{ $n }} ? 'border-[#E10600] bg-[#E10600] text-white' : 'border-[var(--al-border)] bg-[var(--al-surface-2)] text-theme'"
                                    @click="compareCount = {{ $n }}; trimSelection()">
                                {{ $n }}
                            </button>
                        @endforeach
                        <input type="hidden" name="compare_count" :value="compareCount">
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-muted">Sources in comparison</div>
                        <div class="text-[11px] font-semibold" :class="selectionValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted'">
                            <span x-text="selected.length"></span> / <span x-text="compareCount"></span> selected
                        </div>
                    </div>

                    @if($uploads->isEmpty())
                        <p class="rounded-xl px-4 py-3 text-sm text-muted" style="background: var(--al-surface-2); border: 1px solid var(--al-border)">
                            Upload at least 2 sources above before setting up a comparison.
                        </p>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($labels as $key => $label)
                                @php $ready = $uploads->has($key); @endphp
                                <label class="flex items-start gap-3 rounded-xl border px-3 py-3 transition {{ $ready ? 'cursor-pointer' : 'opacity-45 cursor-not-allowed' }}"
                                       style="border-color: var(--al-border); background: var(--al-surface-2)"
                                       :class="selected.includes('{{ $key }}') ? '!border-[#E10600] ring-1 ring-[#E10600]/40' : ''">
                                    <input type="checkbox"
                                           name="sources[]"
                                           value="{{ $key }}"
                                           class="mt-1 h-4 w-4 rounded border-zinc-400 text-[#E10600] focus:ring-[#E10600]"
                                           @disabled(! $ready)
                                           x-model="selected"
                                           @change="onToggle('{{ $key }}')"
                                           @checked(in_array($key, old('sources', $selection['sources'] ?? []), true))>
                                    <span>
                                        <span class="block text-sm font-semibold text-theme">{{ $label }}</span>
                                        @if($ready)
                                            <span class="block text-[11px] text-muted">{{ $uploads[$key]->original_name }}</span>
                                        @else
                                            <span class="block text-[11px] text-muted">Not uploaded yet</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t pt-5" style="border-color: var(--al-border)">
                    <button type="submit"
                            class="al-btn al-btn-primary disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!canContinue">
                        Continue
                    </button>
                    <button type="button"
                            class="al-btn al-btn-ghost disabled:cursor-not-allowed disabled:opacity-40"
                            disabled
                            title="Comparison rules coming next">
                        Generate Report
                    </button>
                    <span class="text-[11px] text-muted">Next: comparison rules coming</span>
                </div>

                @if(! empty($selection['saved_at']))
                    <p class="text-[11px] text-muted">
                        Last saved selection: {{ implode(', ', array_map(fn ($k) => $labels[$k] ?? $k, $selection['sources'] ?? [])) }}
                        ({{ $selection['compare_count'] ?? '?' }}-way) · {{ \Illuminate\Support\Carbon::parse($selection['saved_at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                    </p>
                @endif
            </form>
        </section>
    </div>

    @push('scripts')
        <script>
            function reportAnalysisCompare(cfg) {
                return {
                    compareCount: cfg.compareCount || 2,
                    selected: Array.isArray(cfg.initialSelected) ? [...cfg.initialSelected] : [],
                    uploaded: cfg.uploaded || [],
                    init() {
                        // A background parse is still running — refresh until it settles.
                        if (cfg.busy) {
                            setTimeout(() => window.location.reload(), 6000);
                        }
                    },
                    get selectionValid() {
                        return this.selected.length === this.compareCount
                            && this.selected.every(k => this.uploaded.includes(k));
                    },
                    get canContinue() {
                        return this.selectionValid && this.uploaded.length >= 2;
                    },
                    onToggle(key) {
                        if (!this.uploaded.includes(key)) {
                            this.selected = this.selected.filter(k => k !== key);
                            return;
                        }
                        if (this.selected.length > this.compareCount) {
                            // Keep latest picks within limit
                            this.selected = this.selected.slice(-this.compareCount);
                        }
                    },
                    trimSelection() {
                        if (this.selected.length > this.compareCount) {
                            this.selected = this.selected.slice(0, this.compareCount);
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
