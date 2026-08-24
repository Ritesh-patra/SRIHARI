<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ $errors->first() }}</div>
        @endif

        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10">
                <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    Field
                </span>
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Field Poles</h1>
                <p class="mt-2 max-w-xl text-sm text-white/75">
                    Poles are created by Field Executives in the app.
                    @if(auth()->user()->canApproveSurveys() || auth()->user()->isAdmin())
                        Managers / Admin can delete poles here — create is not allowed.
                    @else
                        View-only. Delete requires Manager / Admin.
                    @endif
                </p>
            </div>
        </section>

        <section class="al-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="al-table min-w-full">
                    <thead>
                        <tr>
                            <th>Pole</th>
                            <th>DTR</th>
                            <th>Feeder</th>
                            <th>Source</th>
                            <th>Houses</th>
                            <th>Surveys</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($poles as $pole)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-seas-950 text-volt dark:bg-volt dark:text-white">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3M8 5h8l1.2 4.5H6.8L8 5zm-1.2 4.5.9 12.5h8.6l.9-12.5M10 22h4"/>
                                            </svg>
                                        </span>
                                        <div class="font-bold text-theme">{{ $pole->pole_no }}</div>
                                    </div>
                                </td>
                                <td class="text-muted">{{ $pole->dtr?->name ?? '—' }} <span class="text-xs">({{ $pole->dtr?->code }})</span></td>
                                <td class="text-muted">{{ $pole->dtr?->feeder?->name ?? '—' }}</td>
                                <td class="text-muted">{{ $pole->source_type === 'previous_pole' ? 'Previous pole' : 'DTR' }}</td>
                                <td class="font-semibold text-theme">{{ $pole->houses_connected }}</td>
                                <td class="text-muted">{{ $pole->consumer_surveys_count }}</td>
                                <td>
                                    @if(auth()->user()->canApproveSurveys() || auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.poles.destroy', $pole) }}" onsubmit="return confirm('Delete pole {{ $pole->pole_no }}? Linked consumer surveys on this pole will also be removed.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs font-bold text-volt hover:underline">Delete</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-muted">No field poles yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t px-5 py-3" style="border-color: var(--al-border)">{{ $poles->links() }}</div>
        </section>
    </div>
</x-app-layout>
