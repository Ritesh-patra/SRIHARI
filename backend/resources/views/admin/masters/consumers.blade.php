<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Master data</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">Consumers</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.masters') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">← Masters</a>
                <a href="{{ route('admin.masters.dtrs.index') }}" class="seas-btn-secondary !py-2 !px-3 text-xs">DTRs</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-5 animate-fade-up">
        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('admin.masters.consumers.index') }}" class="rounded-full px-3 py-1.5 {{ !$source ? 'bg-seas-950 text-white' : 'bg-seas-100 text-seas-600' }}">All {{ number_format($sourceCounts['all']) }}</a>
            <a href="{{ route('admin.masters.consumers.index', ['source' => 'mi', 'q' => $q]) }}" class="rounded-full px-3 py-1.5 {{ $source === 'mi' ? 'bg-seas-950 text-white' : 'bg-seas-100 text-seas-600' }}">MI {{ number_format($sourceCounts['mi']) }}</a>
            <a href="{{ route('admin.masters.consumers.index', ['source' => 'master', 'q' => $q]) }}" class="rounded-full px-3 py-1.5 {{ $source === 'master' ? 'bg-seas-950 text-white' : 'bg-seas-100 text-seas-600' }}">Master {{ number_format($sourceCounts['master']) }}</a>
        </div>

        <form method="GET" class="seas-card flex flex-wrap gap-3 p-4">
            @if($source)<input type="hidden" name="source" value="{{ $source }}">@endif
            <input type="search" name="q" value="{{ $q }}" placeholder="Search IVRS / MSN / name / phone…" class="seas-input min-w-[220px] flex-1">
            <button class="seas-btn-primary !py-2 !px-4 text-xs">Search</button>
        </form>

        <div class="seas-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-seas-950 text-xs uppercase tracking-wide text-white/70">
                        <tr>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">IVRS</th>
                            <th class="px-4 py-3">MSN</th>
                            <th class="px-4 py-3">DTR</th>
                            <th class="px-4 py-3">Feeder</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consumers as $c)
                            <tr class="border-t border-seas-100">
                                <td class="px-4 py-2.5">
                                    <span class="rounded bg-seas-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-seas-600">{{ $c->source ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-2.5 font-semibold text-seas-900">{{ $c->name ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $c->ivrs ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $c->msn ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-seas-500">{{ $c->dtr?->code }}</td>
                                <td class="px-4 py-2.5 text-seas-500">{{ $c->feeder?->code }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-seas-400">No consumers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-seas-100 px-4 py-3">{{ $consumers->links() }}</div>
        </div>
    </div>
</x-app-layout>
