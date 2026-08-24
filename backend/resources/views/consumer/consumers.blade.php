<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Pole → Consumers</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900">{{ $pole->pole_no }}</h1>
                <p class="text-xs text-seas-400">{{ $survey->dtr_name }} · {{ $survey->feeder_name }}</p>
            </div>
            <a href="{{ route('consumer.poles', $survey) }}" class="seas-btn-secondary text-xs">← Pole List</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5 animate-fade-up">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif

        <section class="seas-card p-5">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <div class="text-[10px] font-bold uppercase text-seas-400">Houses on pole</div>
                    <div class="font-display text-2xl font-extrabold text-seas-900">{{ $pole->houses_connected }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase text-seas-400">Master linked</div>
                    <div class="font-display text-2xl font-extrabold">{{ $masterConsumers->count() }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase text-seas-400">Surveyed</div>
                    <div class="font-display text-2xl font-extrabold text-volt">{{ $summary['saved'] ?? $savedSurveys->count() }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase text-seas-400">Flags</div>
                    <div class="text-xs mt-1 text-seas-600">
                        New {{ $summary['new'] ?? 0 }} · NA {{ $summary['not_accessible'] ?? 0 }} · PDC {{ $summary['pdc'] ?? 0 }}
                    </div>
                </div>
            </div>
            <p class="mt-3 text-xs text-seas-400">
                Verify consumers on this pole (phone + meter). Smart-meter usage is captured here,
                then matched later against DTR / Substation meters.
            </p>
        </section>

        <form method="GET" class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-seas-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search consumer (name / phone / IVRS / MSN)" class="seas-input !pl-11">
        </form>

        <div class="seas-card overflow-hidden">
            <div class="border-b border-seas-100 px-4 py-3 font-display font-extrabold text-seas-900">
                Master consumers on this DTR / pole
            </div>
            <div class="divide-y divide-seas-100">
                @forelse($masterConsumers as $c)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="font-semibold text-seas-900">{{ $c->name ?? 'Consumer' }}</div>
                            <div class="text-sm font-bold text-volt">{{ $c->phone ?? 'No phone' }}</div>
                            <div class="text-[11px] text-seas-400">IVRS {{ $c->ivrs ?? '—' }} · MSN {{ $c->msn ?? '—' }}
                                @if(!$c->pole_id)<span class="ml-1 rounded bg-seas-100 px-1.5 py-0.5 text-[10px] font-bold">Unlinked</span>@endif
                            </div>
                        </div>
                        @if(auth()->user()->isFieldExecutive() || auth()->user()->isAdmin())
                            <a href="{{ route('consumer.verify', [$survey, $pole, $c]) }}" class="seas-btn-primary !px-3 !py-2 text-xs shrink-0">Verify</a>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-10 text-center text-seas-400 text-sm">No master consumers found. Add new consumer below.</div>
                @endforelse
            </div>
        </div>

        @if(auth()->user()->isFieldExecutive() || auth()->user()->isAdmin())
            <a href="{{ route('consumer.verify', [$survey, $pole]) }}" class="seas-btn bg-seas-950 text-white w-full py-3.5">
                + Add new consumer on this pole
            </a>
        @endif

        <div class="seas-card overflow-hidden">
            <div class="border-b border-seas-100 px-4 py-3 font-display font-extrabold">Saved in this survey</div>
            <ul class="divide-y divide-seas-100">
                @forelse($savedSurveys as $s)
                    <li class="flex justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <div class="font-semibold">{{ $s->consumer_name ?? 'Consumer' }}</div>
                            <div class="font-bold text-volt">{{ $s->phone }}</div>
                            @if($s->survey_flag)
                                <span class="text-[10px] font-bold uppercase text-seas-400">{{ $s->survey_flag }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-seas-400">{{ $s->surveyed_at?->format('d M, H:i') }}</div>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-seas-400">No consumers saved yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
