<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Analytics
                    </span>
                    <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Reports</h1>
                    <p class="mt-2 text-sm text-white/75">Survey pipeline, coverage, and field productivity.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <a href="{{ route('reports.surveyors') }}" class="al-btn al-btn-primary">Surveyor reports</a>
                    <a href="{{ route('reports.export.surveys') }}" class="al-btn al-btn-light px-4 py-2.5 text-sm">Export Surveys CSV</a>
                    <a href="{{ route('reports.export.consumers') }}" class="al-btn al-btn-light px-4 py-2.5 text-sm">Export Consumers CSV</a>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($byStatus as $status => $total)
                <div class="al-panel p-5">
                    <div class="al-display text-3xl font-bold text-theme">{{ $total }}</div>
                    <div class="mt-1 text-[11px] font-bold uppercase tracking-wide text-muted">{{ str_replace('_',' ', $status) }}</div>
                </div>
            @endforeach
            <div class="al-panel p-5">
                <div class="al-display text-3xl font-bold text-volt">{{ $aging }}</div>
                <div class="mt-1 text-[11px] font-bold uppercase tracking-wide text-muted">Aging pending (3d+)</div>
            </div>
            <div class="al-panel p-5">
                <div class="al-display text-3xl font-bold text-theme">{{ $photoComplete }}</div>
                <div class="mt-1 text-[11px] font-bold uppercase tracking-wide text-muted">Photo complete</div>
            </div>
            <div class="al-panel p-5">
                <div class="al-display text-3xl font-bold text-theme">{{ $consumerCoverage }}</div>
                <div class="mt-1 text-[11px] font-bold uppercase tracking-wide text-muted">Consumers surveyed</div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="al-panel p-5 sm:p-6">
                <h3 class="al-display text-lg font-bold text-theme">Meter install %</h3>
                <div class="mt-4 space-y-3">
                    @foreach($meterInstall as $label => $count)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="font-bold text-theme">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="al-panel p-5 sm:p-6">
                <h3 class="al-display text-lg font-bold text-theme">FE productivity</h3>
                <div class="mt-4 space-y-3">
                    @forelse($feProductivity as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted">{{ $row->surveyor?->name ?? '—' }}</span>
                            <span class="font-bold text-theme">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted">No data</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="al-panel overflow-hidden">
            <div class="border-b px-5 py-4 al-display font-bold text-theme" style="border-color: var(--al-border)">Recent rejection reasons</div>
            <div class="divide-y" style="border-color: var(--al-border)">
                @forelse($rejectionReasons as $r)
                    <div class="px-5 py-3 text-sm" style="border-color: var(--al-border)">
                        <div class="font-semibold text-theme">{{ $r->dtr_name }}</div>
                        <div class="text-muted">{{ $r->review_remarks }}</div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-muted">No rejections</div>
                @endforelse
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <h3 class="al-display mb-4 text-lg font-bold text-theme">Last 14 days submissions</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($daily as $d)
                    <div class="rounded-xl px-3 py-2 text-center" style="background: var(--al-surface-2); border: 1px solid var(--al-border)">
                        <div class="text-[10px] text-muted">{{ $d->day }}</div>
                        <div class="al-display font-bold text-theme">{{ $d->total }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
