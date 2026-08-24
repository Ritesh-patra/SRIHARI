<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Master data</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">DTRs</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.masters') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">← Masters</a>
                <a href="{{ route('admin.masters.consumers.index') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">Consumers</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-5 animate-fade-up">
        <form method="GET" class="seas-card flex flex-wrap gap-3 p-4">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search DTR code / name / feeder…" class="seas-input min-w-[220px] flex-1">
            <button class="seas-btn-primary !py-2 !px-4 text-xs">Search</button>
        </form>

        <div class="seas-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-seas-950 text-xs uppercase tracking-wide text-white/70">
                        <tr>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Feeder</th>
                            <th class="px-4 py-3">kVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dtrs as $dtr)
                            <tr class="border-t border-seas-100">
                                <td class="px-4 py-2.5 font-semibold text-seas-900">{{ $dtr->code }}</td>
                                <td class="px-4 py-2.5">{{ $dtr->name }}</td>
                                <td class="px-4 py-2.5 text-seas-500">{{ $dtr->feeder?->code }} · {{ $dtr->feeder?->name }}</td>
                                <td class="px-4 py-2.5">{{ $dtr->capacity_kva ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-seas-400">No DTRs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-seas-100 px-4 py-3">{{ $dtrs->links() }}</div>
        </div>
    </div>
</x-app-layout>
