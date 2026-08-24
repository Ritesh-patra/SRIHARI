<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Approved DTRs only</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">Consumer Survey</h1>
                <p class="mt-1 text-sm text-seas-400">Add poles → verify consumers → complete the energy trail</p>
            </div>
            <a href="{{ route('dashboard') }}" class="seas-btn-secondary text-xs">← Dashboard</a>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-6 sm:space-y-6 lg:space-y-8 animate-fade-up">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif

        <div class="seas-card p-5 text-sm text-seas-600">
            <div class="font-display font-extrabold text-seas-900 mb-2">SEAS energy path</div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                <span class="rounded-lg bg-seas-950 px-2.5 py-1 text-white">Substation</span>
                <span class="text-seas-300">→</span>
                <span class="rounded-lg bg-seas-950 px-2.5 py-1 text-white">Feeder</span>
                <span class="text-seas-300">→</span>
                <span class="rounded-lg bg-seas-950 px-2.5 py-1 text-white">DTR + Meter</span>
                <span class="text-seas-300">→</span>
                <span class="rounded-lg bg-volt px-2.5 py-1 text-white">Pole</span>
                <span class="text-seas-300">→</span>
                <span class="rounded-lg bg-seas-950 px-2.5 py-1 text-white">Consumer + Smart Meter</span>
            </div>
            <p class="mt-3 text-xs">
                Survey goal: count consumers per pole, verify meters, then match totals against
                Substation / DTR meter readings to detect loss or mismatch.
            </p>
        </div>

        <div class="seas-table-wrap">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>DTR</th>
                            <th>Feeder</th>
                            <th>Poles</th>
                            <th>Houses</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($surveys as $survey)
                            <tr>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-seas-900">{{ $survey->dtr_name }}</div>
                                    <div class="text-xs text-slate-400">{{ $survey->dtr_code }}</div>
                                </td>
                                <td class="px-4 py-3.5">{{ $survey->feeder_name }}</td>
                                <td class="px-4 py-3.5 font-display text-lg font-bold">{{ $survey->dtr?->poles?->count() ?? 0 }}</td>
                                <td class="px-4 py-3.5 font-display text-lg font-bold text-volt">{{ $survey->dtr?->poles?->sum('houses_connected') ?? 0 }}</td>
                                <td class="px-4 py-3.5 text-right">
                                    <a href="{{ route('consumer.poles', $survey) }}" class="seas-btn-primary text-xs !py-2 !px-3">Open Pole List →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-seas-400">
                                    No Approved DTR yet. Pehle DTR Survey submit karke Manager approval lo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-seas-100 px-4 py-3">{{ $surveys->links() }}</div>
        </div>
    </div>
</x-app-layout>
