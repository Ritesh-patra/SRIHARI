<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <section class="al-hero relative p-6">
            <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">Inbox</span>
                    <h1 class="al-display mt-3 text-3xl font-bold text-white">Notifications</h1>
                </div>
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button class="al-btn al-btn-light px-4 py-2.5 text-sm">Mark all read</button>
                </form>
            </div>
        </section>

        <div class="al-panel overflow-hidden divide-y" style="border-color: var(--al-border)">
            @forelse($notifications as $n)
                <a href="{{ route('notifications.read', $n) }}" class="block px-5 py-4 transition hover:opacity-90 {{ $n->read_at ? 'opacity-60' : '' }}" style="border-color: var(--al-border)">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-bold text-theme">{{ $n->title }}</div>
                            <div class="text-sm text-muted">{{ $n->body }}</div>
                            <div class="mt-1 text-[11px] text-muted">{{ $n->created_at?->diffForHumans() }}</div>
                        </div>
                        @unless($n->read_at)
                            <span class="mt-2 h-2 w-2 rounded-full bg-volt"></span>
                        @endunless
                    </div>
                </a>
            @empty
                <div class="px-5 py-12 text-center text-muted">No notifications yet.</div>
            @endforelse
        </div>
        {{ $notifications->links() }}
    </div>
</x-app-layout>
