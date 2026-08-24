<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6" x-data="{
        feederSel: [], dtrSel: [], consumerSel: [],
        sync(list, name) {
            this[list] = [...document.querySelectorAll('input.' + name + ':checked')].map(b => b.value);
        },
        toggleAll(e, list, name) {
            const boxes = [...document.querySelectorAll('input.' + name)];
            boxes.forEach(b => b.checked = e.target.checked);
            this[list] = e.target.checked ? boxes.map(b => b.value) : [];
        }
    }">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">Individual report</span>
                    <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">{{ $surveyor->name }}</h1>
                    <p class="mt-2 text-sm text-white/75">{{ $surveyor->email }} · {{ $surveyor->roleLabel() }} · {{ $from }} → {{ $to }}</p>
                </div>
                <a href="{{ route('reports.surveyors', ['from' => $from, 'to' => $to, 'role' => $surveyor->role]) }}"
                   class="al-btn al-btn-light px-4 py-2.5 text-sm">← All reports</a>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach([
                ['Pending', $summary['pending'], 'text-amber-600'],
                ['Approved', $summary['approved'], 'text-emerald-600'],
                ['Rejected', $summary['rejected'], 'text-volt'],
                ['Completed', $summary['completed'], 'text-theme'],
                ['Total surveyed', $summary['total'], 'text-[#E10600]'],
            ] as [$label, $val, $cls])
                <div class="al-panel p-5">
                    <div class="al-display text-3xl font-bold {{ $cls }}">{{ $val }}</div>
                    <div class="mt-1 text-[11px] font-bold uppercase tracking-wide text-muted">{{ $label }}</div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="al-panel p-5">
                <h3 class="al-display text-lg font-bold text-theme">Which feeders surveyed?</h3>
                @if(!empty($summary['feeder_names']))
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-theme">
                        @foreach($summary['feeder_names'] as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-muted">No feeder surveys in this date range.</p>
                @endif
            </div>
            <div class="al-panel p-5">
                <h3 class="al-display text-lg font-bold text-theme">Which DTRs surveyed?</h3>
                @if(!empty($summary['dtr_names']))
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-theme">
                        @foreach($summary['dtr_names'] as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-muted">No DTR surveys in this date range.</p>
                @endif
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            @foreach([
                ['Feeder surveys', $summary['feeder']],
                ['DTR surveys', $summary['dtr']],
                ['Consumer surveys', $summary['consumer']],
            ] as [$title, $bucket])
                <div class="al-panel p-5">
                    <h3 class="al-display text-lg font-bold text-theme">{{ $title }}</h3>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Pending</span><span class="font-bold">{{ $bucket['pending'] }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Approved</span><span class="font-bold">{{ $bucket['approved'] }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Rejected</span><span class="font-bold">{{ $bucket['rejected'] }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Completed</span><span class="font-bold">{{ $bucket['completed'] }}</span></div>
                        <div class="flex justify-between border-t pt-2" style="border-color: var(--al-border)">
                            <span class="font-bold text-theme">Total</span>
                            <span class="al-display font-bold text-[#E10600]">{{ $bucket['total'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Feeder --}}
        <section class="al-panel overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4" style="border-color: var(--al-border)">
                <div class="al-display font-bold text-theme">Feeder surveys ({{ $feeders->count() }})</div>
                <form method="POST" action="{{ route('reports.surveys.delete') }}" class="flex items-center gap-2"
                      @submit="if (feederSel.length === 0) { $event.preventDefault(); return false; } if (!confirm('Permanently delete ' + feederSel.length + ' feeder survey(s)?')) $event.preventDefault();">
                    @csrf
                    <input type="hidden" name="type" value="feeder">
                    <input type="hidden" name="surveyor_id" value="{{ $surveyor->id }}">
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <template x-for="id in feederSel" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-bold text-white disabled:opacity-40" :disabled="feederSel.length === 0">
                        Delete selected (<span x-text="feederSel.length"></span>)
                    </button>
                </form>
            </div>
            <div class="divide-y" style="border-color: var(--al-border)">
                @if($feeders->isNotEmpty())
                    <div class="flex items-center gap-2 px-5 py-2 text-xs text-muted" style="background: var(--al-surface-2)">
                        <input type="checkbox" @change="toggleAll($event, 'feederSel', 'feeder-check')"> Select all
                    </div>
                @endif
                @forelse($feeders as $f)
                    <div class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--al-border)">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" class="feeder-check mt-1" value="{{ $f->id }}" @change="sync('feederSel', 'feeder-check')">
                            <div>
                                <div class="font-semibold text-theme">{{ $f->feeder_name ?? 'Feeder #'.$f->feeder_id }}</div>
                                <div class="text-xs text-muted">{{ $f->substation_name }} · {{ $f->surveyed_at?->format('d M Y H:i') ?? $f->created_at?->format('d M Y H:i') }}</div>
                            </div>
                        </div>
                        <span class="rounded-lg px-2.5 py-1 text-xs font-bold" style="background: var(--al-surface-2)">{{ $f->display_status ?? $f->status }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-muted">No feeder surveys in range.</div>
                @endforelse
            </div>
        </section>

        {{-- DTR --}}
        <section class="al-panel overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4" style="border-color: var(--al-border)">
                <div class="al-display font-bold text-theme">DTR surveys ({{ $dtrs->count() }})</div>
                <form method="POST" action="{{ route('reports.surveys.delete') }}" class="flex items-center gap-2"
                      @submit="if (dtrSel.length === 0) { $event.preventDefault(); return false; } if (!confirm('Permanently delete ' + dtrSel.length + ' DTR survey(s)? Linked consumer surveys may also be removed.')) $event.preventDefault();">
                    @csrf
                    <input type="hidden" name="type" value="dtr">
                    <input type="hidden" name="surveyor_id" value="{{ $surveyor->id }}">
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <template x-for="id in dtrSel" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-bold text-white disabled:opacity-40" :disabled="dtrSel.length === 0">
                        Delete selected (<span x-text="dtrSel.length"></span>)
                    </button>
                </form>
            </div>
            <div class="divide-y" style="border-color: var(--al-border)">
                @if($dtrs->isNotEmpty())
                    <div class="flex items-center gap-2 px-5 py-2 text-xs text-muted" style="background: var(--al-surface-2)">
                        <input type="checkbox" @change="toggleAll($event, 'dtrSel', 'dtr-check')"> Select all
                    </div>
                @endif
                @forelse($dtrs as $d)
                    <div class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--al-border)">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" class="dtr-check mt-1" value="{{ $d->id }}" @change="sync('dtrSel', 'dtr-check')">
                            <div>
                                <div class="font-semibold text-theme">{{ $d->dtr_name }} <span class="text-muted">({{ $d->dtr_code }})</span></div>
                                <div class="text-xs text-muted">{{ $d->feeder_name }} · {{ $d->surveyed_at?->format('d M Y H:i') ?? $d->created_at?->format('d M Y H:i') }}</div>
                            </div>
                        </div>
                        <span class="rounded-lg px-2.5 py-1 text-xs font-bold" style="background: var(--al-surface-2)">{{ $d->displayStatusLabel() }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-muted">No DTR surveys in range.</div>
                @endforelse
            </div>
        </section>

        {{-- Consumer --}}
        <section class="al-panel overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4" style="border-color: var(--al-border)">
                <div class="al-display font-bold text-theme">
                    Consumer surveys ({{ $consumers->count() }}{{ $consumers->count() >= 200 ? '+' : '' }})
                </div>
                <form method="POST" action="{{ route('reports.surveys.delete') }}" class="flex items-center gap-2"
                      @submit="if (consumerSel.length === 0) { $event.preventDefault(); return false; } if (!confirm('Permanently delete ' + consumerSel.length + ' consumer survey(s)?')) $event.preventDefault();">
                    @csrf
                    <input type="hidden" name="type" value="consumer">
                    <input type="hidden" name="surveyor_id" value="{{ $surveyor->id }}">
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <template x-for="id in consumerSel" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-bold text-white disabled:opacity-40" :disabled="consumerSel.length === 0">
                        Delete selected (<span x-text="consumerSel.length"></span>)
                    </button>
                </form>
            </div>
            <div class="divide-y" style="border-color: var(--al-border)">
                @if($consumers->isNotEmpty())
                    <div class="flex items-center gap-2 px-5 py-2 text-xs text-muted" style="background: var(--al-surface-2)">
                        <input type="checkbox" @change="toggleAll($event, 'consumerSel', 'consumer-check')"> Select all
                    </div>
                @endif
                @forelse($consumers as $c)
                    <div class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--al-border)">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" class="consumer-check mt-1" value="{{ $c->id }}" @change="sync('consumerSel', 'consumer-check')">
                            <div>
                                <div class="font-semibold text-theme">{{ $c->consumer_name ?? 'Consumer' }}</div>
                                <div class="text-xs text-muted">
                                    Pole {{ $c->pole?->pole_no ?? '—' }}
                                    · {{ $c->dtr?->name ?? 'DTR' }}
                                    · IVRS {{ $c->ivrs ?? '—' }}
                                    · {{ $c->surveyed_at?->format('d M Y H:i') }}
                                </div>
                            </div>
                        </div>
                        <span class="rounded-lg px-2.5 py-1 text-xs font-bold" style="background: var(--al-surface-2)">
                            {{ $c->status ?? $c->survey_flag ?? 'saved' }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-muted">No consumer surveys in range.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
