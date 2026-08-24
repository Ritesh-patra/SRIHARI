<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10">
                <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">Audit trail</span>
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Activity Log</h1>
                <p class="mt-2 text-sm text-white/75">Login, survey submit/approve, master changes, and assignments.</p>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="al-panel p-4">
                <div class="text-[10px] font-bold uppercase text-muted">Events</div>
                <div class="al-display text-3xl font-bold text-theme">{{ $logs->total() }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-[10px] font-bold uppercase text-muted">This page</div>
                <div class="al-display text-3xl font-bold text-volt">{{ $logs->count() }}</div>
            </div>
            <div class="al-panel col-span-2 flex items-center gap-3 p-4">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-seas-950 text-white dark:bg-volt">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4l3 3M12 3a9 9 0 1 0 0 18"/></svg>
                </span>
                <div class="text-sm text-muted">Newest events first — full system timeline.</div>
            </div>
        </div>

        <div class="al-panel overflow-hidden">
            <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--al-border)">
                <h3 class="al-display font-bold text-theme">Recent events</h3>
                <span class="text-xs font-bold text-muted">Newest first</span>
            </div>
            <div class="divide-y" style="border-color: var(--al-border)">
                @forelse($logs as $log)
                    <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--al-border)">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-volt/15 text-volt">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M12 3a9 9 0 1 0 0 18"/></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="font-bold text-theme">{{ $log->action }}</div>
                                <div class="text-sm text-muted">{{ $log->user?->name ?? 'System' }}</div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold sm:justify-end">
                            <span class="rounded-lg px-2.5 py-1 text-muted" style="background: var(--al-surface-2)">{{ $log->created_at?->format('d M Y · H:i') }}</span>
                            <span class="rounded-lg px-2.5 py-1 text-muted" style="background: var(--al-surface-2)">IP {{ $log->ip_address ?? '—' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-16 text-center text-muted">No activity yet.</div>
                @endforelse
            </div>
            <div class="border-t px-5 py-3" style="border-color: var(--al-border)">{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
