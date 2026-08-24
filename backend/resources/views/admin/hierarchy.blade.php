<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Network map</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">Master Hierarchy</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.masters.dtrs.index') }}" class="seas-btn-primary !py-2 !px-3 text-xs">DTR list</a>
                <a href="{{ route('admin.masters.consumers.index') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">Consumers</a>
                <a href="{{ route('admin.masters') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">Manage Masters</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-5 sm:space-y-6 lg:space-y-8 animate-fade-up">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach([
                ['Regions', $stats['regions'] ?? 0],
                ['Zones', $stats['zones'] ?? 0],
                ['Feeders', $stats['feeders'] ?? 0],
                ['DTRs', $stats['dtrs'] ?? 0],
            ] as [$label, $val])
                <div class="seas-card p-4">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-seas-400">{{ $label }}</div>
                    <div class="mt-1 font-display text-2xl font-extrabold text-seas-900">{{ number_format($val) }}</div>
                </div>
            @endforeach
        </div>

        <div class="al-panel p-5 text-sm">
            <div class="al-display font-bold text-theme">Summary view</div>
            <p class="mt-1 text-xs text-muted">
                Full feeder/DTR trees are not rendered here ({{ number_format($stats['feeders'] ?? 0) }} feeders · {{ number_format($stats['dtrs'] ?? 0) }} DTRs).
                Use the paginated DTR / Consumer lists for detail.
            </p>
        </div>

        <section class="seas-card p-5">
            <h2 class="font-display font-extrabold text-seas-900">Regions</h2>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach($regions as $region)
                    <li class="rounded-xl border border-seas-100 px-4 py-3 flex items-center justify-between">
                        <span class="font-semibold text-seas-900">{{ $region->name }}</span>
                        <span class="text-xs text-seas-400">{{ $region->circles_count }} circles</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="seas-card overflow-hidden">
            <div class="border-b border-seas-100 px-5 py-3 font-display font-extrabold text-seas-900">Zones (paginated)</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-seas-950 text-xs uppercase tracking-wide text-white/70">
                        <tr>
                            <th class="px-4 py-3">Zone</th>
                            <th class="px-4 py-3">Division</th>
                            <th class="px-4 py-3">Circle</th>
                            <th class="px-4 py-3">Region</th>
                            <th class="px-4 py-3">SS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($zones as $zone)
                            <tr class="border-t border-seas-100">
                                <td class="px-4 py-2.5 font-semibold text-seas-900">{{ $zone->name }}</td>
                                <td class="px-4 py-2.5">{{ $zone->division?->name }}</td>
                                <td class="px-4 py-2.5">{{ $zone->division?->circle?->name }}</td>
                                <td class="px-4 py-2.5">{{ $zone->division?->circle?->region?->name }}</td>
                                <td class="px-4 py-2.5">{{ $zone->substations_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-seas-400">No zones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-seas-100 px-4 py-3">{{ $zones->links() }}</div>
        </section>
    </div>
</x-app-layout>
