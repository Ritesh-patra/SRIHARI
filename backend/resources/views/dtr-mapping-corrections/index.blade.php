<x-app-layout>
    @php
        $statusColors = [
            'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
            'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
            'rejected' => 'bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-200',
        ];
    @endphp

    <div class="mx-auto w-full space-y-5 sm:space-y-6">
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
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">DTR Mapping Corrections</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">
                    Field teams reported DTR codes mapped under a different feeder. Approve to update master feeder mapping, or reject to keep master unchanged. Survey field data is always retained.
                </p>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-3">
            <a href="{{ route('dtr-mapping-corrections.index', ['status' => 'pending']) }}" class="al-panel px-4 py-3 {{ $status === 'pending' ? 'ring-2 ring-volt' : '' }}">
                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Pending</div>
                <div class="al-display mt-1 text-2xl font-extrabold" style="color: #E10600;">{{ $counts['pending'] }}</div>
            </a>
            <a href="{{ route('dtr-mapping-corrections.index', ['status' => 'approved']) }}" class="al-panel px-4 py-3 {{ $status === 'approved' ? 'ring-2 ring-volt' : '' }}">
                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Approved</div>
                <div class="al-display mt-1 text-2xl font-extrabold text-theme" style="color: var(--al-text)">{{ $counts['approved'] }}</div>
            </a>
            <a href="{{ route('dtr-mapping-corrections.index', ['status' => 'rejected']) }}" class="al-panel px-4 py-3 {{ $status === 'rejected' ? 'ring-2 ring-volt' : '' }}">
                <div class="text-[11px] font-bold uppercase tracking-wider text-muted">Rejected</div>
                <div class="al-display mt-1 text-2xl font-extrabold text-theme" style="color: var(--al-text)">{{ $counts['rejected'] }}</div>
            </a>
        </div>

        <section class="al-panel overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--al-border)">
                <div>
                    <h2 class="font-bold text-theme">Queue · {{ ucfirst($status) }}</h2>
                    <p class="text-xs text-muted">Master feeder is not changed until you Approve.</p>
                </div>
                <a href="{{ route('dtr-mapping-corrections.index', ['status' => 'all']) }}" class="text-xs font-bold text-volt hover:underline">View all</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-muted" style="background: var(--al-surface-2)">
                            <th class="px-4 py-3">Survey</th>
                            <th class="px-4 py-3">DTR</th>
                            <th class="px-4 py-3">Master feeder</th>
                            <th class="px-4 py-3">Reported feeder</th>
                            <th class="px-4 py-3">Surveyor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($corrections as $row)
                            @php
                                $master = $row->masterFeeder;
                                $reported = $row->reportedFeeder;
                            @endphp
                            <tr class="border-t align-top" style="border-color: var(--al-border)">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-theme">#{{ $row->id }}</div>
                                    <div class="text-xs text-muted">{{ $row->surveyed_at?->format('d M Y, H:i') ?? $row->created_at?->format('d M Y, H:i') }}</div>
                                    <div class="mt-1 text-[11px] text-muted">Survey: {{ $row->status }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-theme">{{ $row->dtr_code }}</div>
                                    <div class="text-xs text-muted">{{ $row->field_dtr_name ?: $row->dtr_name }}</div>
                                    <div class="text-[11px] text-muted">{{ $row->dtr_capacity_kva ? $row->dtr_capacity_kva.' kVA' : '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-theme">{{ $master?->code ?? '—' }}</div>
                                    <div class="text-xs text-muted">{{ $master?->name }}</div>
                                    <div class="text-[11px] text-muted">{{ $master?->substation?->name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-theme">{{ $reported?->code ?? $row->feeder_code }}</div>
                                    <div class="text-xs text-muted">{{ $reported?->name ?? $row->feeder_name }}</div>
                                    <div class="text-[11px] text-muted">{{ $reported?->substation?->name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-theme">{{ $row->surveyor?->name ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusColors[$row->mapping_correction_status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $row->mappingCorrectionLabel() ?? ucfirst($row->mapping_correction_status) }}
                                    </span>
                                    @if($row->mapping_correction_remarks)
                                        <p class="mt-2 max-w-[220px] text-[11px] text-muted">{{ $row->mapping_correction_remarks }}</p>
                                    @endif
                                    @if($row->mappingCorrectionReviewer)
                                        <p class="mt-1 text-[11px] text-muted">By {{ $row->mappingCorrectionReviewer->name }} · {{ $row->mapping_correction_reviewed_at?->format('d M Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($row->mapping_correction_status === \App\Models\DtrSurvey::MAPPING_PENDING)
                                        <form method="POST" action="{{ route('dtr-mapping-corrections.approve', $row) }}" class="mb-2 space-y-2">
                                            @csrf
                                            <input type="text" name="review_remarks" class="al-input text-xs" placeholder="Optional remarks">
                                            <button type="submit" class="al-btn al-btn-primary w-full text-xs" onclick="return confirm('Approve mapping? Master DTR will move to the reported feeder.')">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('dtr-mapping-corrections.reject', $row) }}" class="space-y-2">
                                            @csrf
                                            <input type="text" name="review_remarks" class="al-input text-xs" placeholder="Reject reason (required)" required>
                                            <button type="submit" class="al-btn w-full text-xs" style="background: var(--al-surface-2); color: var(--al-text);" onclick="return confirm('Reject mapping? Master feeder stays unchanged; survey data is kept.')">Reject</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-muted">No actions</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">No mapping correction requests in this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($corrections->hasPages())
                <div class="border-t px-5 py-4" style="border-color: var(--al-border)">
                    {{ $corrections->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
