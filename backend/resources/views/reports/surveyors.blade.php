<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#E10600]"></span>
                        Surveyor reports
                    </span>
                    <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Audit Report</h1>
                    <p class="mt-2 text-sm text-white/75">Total audits + per-person breakdown. Open any person to view or delete their surveys.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.export.surveyors', request()->query()) }}"
                       class="al-btn al-btn-primary px-4 py-2.5 text-sm">Export Excel</a>
                    <a href="{{ route('reports.index') }}" class="al-btn al-btn-light px-4 py-2.5 text-sm">Analytics overview</a>
                </div>
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <form method="GET" action="{{ route('reports.surveyors') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="al-input w-full rounded-xl border px-3 py-2.5 text-sm" style="border-color: var(--al-border); background: var(--al-input)">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="al-input w-full rounded-xl border px-3 py-2.5 text-sm" style="border-color: var(--al-border); background: var(--al-input)">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Role</label>
                    <select name="role" class="al-input w-full rounded-xl border px-3 py-2.5 text-sm" style="border-color: var(--al-border); background: var(--al-input)">
                        <option value="">All roles</option>
                        @foreach($roles as $r)
                            <option value="{{ $r }}" @selected($role === $r)>{{ str_replace('_', ' ', ucfirst($r)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">User</label>
                    <select name="user_id" class="al-input w-full rounded-xl border px-3 py-2.5 text-sm" style="border-color: var(--al-border); background: var(--al-input)">
                        <option value="">All users</option>
                        @foreach($filterUsers as $u)
                            <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="include_empty" value="1" @checked($includeEmpty) class="rounded border-gray-300 text-volt focus:ring-volt">
                        Include empty
                    </label>
                    <button type="submit" class="al-btn al-btn-primary ml-auto">Apply</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            @foreach([
                ['People', $totals['people'] ?? 0, 'text-theme'],
                ['Total audits', $totals['total'] ?? 0, 'text-[#E10600]'],
                ['Feeder', $totals['feeder'] ?? 0, 'text-theme'],
                ['DTR', $totals['dtr'] ?? 0, 'text-theme'],
                ['Consumer', $totals['consumer'] ?? 0, 'text-theme'],
                ['Pending', $totals['pending'] ?? 0, 'text-amber-600'],
                ['Approved', $totals['approved'] ?? 0, 'text-emerald-600'],
                ['Rejected', $totals['rejected'] ?? 0, 'text-volt'],
            ] as [$label, $val, $cls])
                <div class="al-panel p-4">
                    <div class="al-display text-2xl font-bold {{ $cls }}">{{ $val }}</div>
                    <div class="mt-1 text-[10px] font-bold uppercase tracking-wide text-muted">{{ $label }}</div>
                </div>
            @endforeach
        </section>

        <section class="al-panel overflow-hidden">
            <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--al-border)">
                <h3 class="al-display font-bold text-theme">Per person</h3>
                <span class="text-xs font-bold text-muted">{{ $from }} → {{ $to }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-[11px] font-bold uppercase tracking-wide text-muted" style="background: var(--al-surface-2)">
                            <th class="px-5 py-3">User</th>
                            <th class="px-3 py-3">Pending</th>
                            <th class="px-3 py-3">Approved</th>
                            <th class="px-3 py-3">Rejected</th>
                            <th class="px-3 py-3">Total</th>
                            <th class="px-3 py-3 min-w-[220px]">Feeders surveyed</th>
                            <th class="px-3 py-3 min-w-[240px]">DTRs surveyed</th>
                            <th class="px-3 py-3">Consumer</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--al-border)">
                        @forelse($rows as $row)
                            @php $u = $row['user']; @endphp
                            <tr style="border-color: var(--al-border)">
                                <td class="px-5 py-3">
                                    <div class="font-bold text-theme">{{ $u->name }}</div>
                                    <div class="text-xs text-muted">{{ $u->email }} · {{ $u->roleLabel() }}</div>
                                </td>
                                <td class="px-3 py-3 font-semibold text-amber-600">{{ $row['pending'] }}</td>
                                <td class="px-3 py-3 font-semibold text-emerald-600">{{ $row['approved'] }}</td>
                                <td class="px-3 py-3 font-semibold text-volt">{{ $row['rejected'] }}</td>
                                <td class="px-3 py-3 al-display font-bold text-theme">{{ $row['total'] }}</td>
                                <td class="px-3 py-3 text-xs text-theme">
                                    @if(!empty($row['feeder_names']))
                                        <div class="font-bold text-muted">{{ $row['feeder']['total'] }} feeder(s)</div>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                            @foreach(array_slice($row['feeder_names'], 0, 5) as $name)
                                                <li>{{ $name }}</li>
                                            @endforeach
                                            @if(count($row['feeder_names']) > 5)
                                                <li class="text-muted">+{{ count($row['feeder_names']) - 5 }} more</li>
                                            @endif
                                        </ul>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-theme">
                                    @if(!empty($row['dtr_names']))
                                        <div class="font-bold text-muted">{{ $row['dtr']['total'] }} DTR(s)</div>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                            @foreach(array_slice($row['dtr_names'], 0, 6) as $name)
                                                <li>{{ $name }}</li>
                                            @endforeach
                                            @if(count($row['dtr_names']) > 6)
                                                <li class="text-muted">+{{ count($row['dtr_names']) - 6 }} more · open View</li>
                                            @endif
                                        </ul>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-muted">{{ $row['consumer']['total'] }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('reports.surveyors.show', ['user' => $u->id, 'from' => $from, 'to' => $to]) }}"
                                       class="inline-flex items-center rounded-xl bg-[#E10600] px-3 py-1.5 text-xs font-bold text-white hover:opacity-90">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-16 text-center text-muted">No surveyors found for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
