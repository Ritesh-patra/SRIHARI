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
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">DTR Re-activation</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">
                    Field executives request reopening of finished DTRs to survey more consumers.
                    Approve to clear Finish lock; reject to keep the DTR finished.
                </p>
            </div>
        </section>

        @if(!empty($tableMissing))
            <section class="al-panel px-5 py-8 text-center text-sm text-muted">
                Table <code class="font-mono text-theme">dtr_reactivation_requests</code> is missing.
                Run <strong class="text-theme">ENSURE-dtr-reactivation.sql</strong> (or <code class="font-mono">php artisan migrate</code>), then refresh.
            </section>
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                <a href="{{ route('dtr-reactivation.index', ['status' => 'pending']) }}" class="al-panel px-4 py-3 {{ $status === 'pending' ? 'ring-2 ring-volt' : '' }}">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Pending</div>
                    <div class="al-display mt-1 text-2xl font-extrabold" style="color: #E10600;">{{ $counts['pending'] }}</div>
                </a>
                <a href="{{ route('dtr-reactivation.index', ['status' => 'approved']) }}" class="al-panel px-4 py-3 {{ $status === 'approved' ? 'ring-2 ring-volt' : '' }}">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Approved</div>
                    <div class="al-display mt-1 text-2xl font-extrabold text-theme" style="color: var(--al-text)">{{ $counts['approved'] }}</div>
                </a>
                <a href="{{ route('dtr-reactivation.index', ['status' => 'rejected']) }}" class="al-panel px-4 py-3 {{ $status === 'rejected' ? 'ring-2 ring-volt' : '' }}">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-muted">Rejected</div>
                    <div class="al-display mt-1 text-2xl font-extrabold text-theme" style="color: var(--al-text)">{{ $counts['rejected'] }}</div>
                </a>
            </div>

            <section class="al-panel overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--al-border)">
                    <div>
                        <h2 class="font-bold text-theme">Queue · {{ ucfirst($status) }}</h2>
                        <p class="text-xs text-muted">Approve clears <code class="font-mono">consumer_survey_completed_at</code> so FE can survey again.</p>
                    </div>
                    <a href="{{ route('dtr-reactivation.index', ['status' => 'all']) }}" class="text-xs font-bold text-volt hover:underline">View all</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-muted" style="background: var(--al-surface-2)">
                                <th class="px-4 py-3">Request</th>
                                <th class="px-4 py-3">DTR</th>
                                <th class="px-4 py-3">Feeder</th>
                                <th class="px-4 py-3">Requested by</th>
                                <th class="px-4 py-3">Reason</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $row)
                                @php $survey = $row->dtrSurvey; @endphp
                                <tr class="border-t align-top" style="border-color: var(--al-border)">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-theme">#{{ $row->id }}</div>
                                        <div class="text-xs text-muted">{{ $row->created_at?->format('d M Y, H:i') }}</div>
                                        <div class="mt-1 text-[11px] text-muted">Survey #{{ $row->dtr_survey_id }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-theme">{{ $survey?->dtr_code ?? '—' }}</div>
                                        <div class="text-xs text-muted">{{ $survey?->dtr_name }}</div>
                                        @if($survey?->consumer_survey_completed_at)
                                            <div class="mt-1 text-[11px] font-semibold text-amber-700 dark:text-amber-300">Still finished</div>
                                        @elseif($survey)
                                            <div class="mt-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">Open for consumer</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-theme">{{ $survey?->feeder_code ?? $survey?->feeder?->code ?? '—' }}</div>
                                        <div class="text-xs text-muted">{{ $survey?->feeder_name ?? $survey?->feeder?->name }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-theme">{{ $row->requester?->name ?? '—' }}</div>
                                        <div class="text-[11px] text-muted">Surveyor: {{ $survey?->surveyor?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-[240px] text-xs text-theme">{{ $row->reason ?: '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusColors[$row->status] ?? 'bg-slate-100 text-slate-700' }}">
                                            {{ $row->statusLabel() }}
                                        </span>
                                        @if($row->review_remarks)
                                            <p class="mt-2 max-w-[220px] text-[11px] text-muted">{{ $row->review_remarks }}</p>
                                        @endif
                                        @if($row->reviewer)
                                            <p class="mt-1 text-[11px] text-muted">By {{ $row->reviewer->name }} · {{ $row->reviewed_at?->format('d M Y') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($row->isPending())
                                            <form method="POST" action="{{ route('dtr-reactivation.approve', $row) }}" class="mb-2 space-y-2">
                                                @csrf
                                                <input type="text" name="review_remarks" class="al-input text-xs" placeholder="Optional remarks">
                                                <button type="submit" class="al-btn al-btn-primary w-full text-xs" onclick="return confirm('Approve? DTR will reopen for consumer survey.')">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('dtr-reactivation.reject', $row) }}" class="space-y-2">
                                                @csrf
                                                <input type="text" name="review_remarks" class="al-input text-xs" placeholder="Reject reason (required)" required>
                                                <button type="submit" class="al-btn w-full text-xs" style="background: var(--al-surface-2); color: var(--al-text);" onclick="return confirm('Reject? DTR stays finished.')">Reject</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-muted">No actions</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">No re-activation requests in this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($requests->hasPages())
                    <div class="border-t px-5 py-4" style="border-color: var(--al-border)">
                        {{ $requests->links() }}
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
