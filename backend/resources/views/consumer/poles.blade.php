<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Consumer Survey</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">Pole List</h1>
                <p class="mt-1 text-xs text-seas-400">Substation → Feeder → DTR → <strong class="text-volt">Pole</strong> → Consumer</p>
            </div>
            <a href="{{ route('consumer.index') }}" class="seas-btn-secondary text-xs">← Approved DTRs</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5 animate-fade-up" x-data="{ q: '', showAdd: false }">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="seas-alert-error">
                <ul class="list-disc ms-4 text-sm">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Energy audit context --}}
        <div class="rounded-2xl border border-seas-100 bg-seas-50 px-4 py-3 text-sm text-seas-600">
            <strong class="text-seas-900">Energy audit:</strong>
            Smart meters sit on Substation / DTR / Consumer.
            Surveys compare power delivered vs consumer usage, and track
            <strong>how many consumers are connected on each pole</strong>.
        </div>

        {{-- DTR info card (like mockup) --}}
        <section class="seas-card p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-seas-400">DTR Name</span><span class="font-bold text-seas-900 text-right">{{ $survey->dtr_name }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-seas-400">DTR Code</span><span class="font-bold text-seas-900">{{ $survey->dtr_code }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-seas-400">Feeder Name</span><span class="font-bold text-seas-900 text-right">{{ $survey->feeder_name }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-seas-400">Total Consumers</span><span class="font-extrabold text-seas-900">{{ $stats['total_consumers'] }}</span></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-seas-950 p-4 text-white">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-white/50">Surveyed</div>
                        <div class="mt-1 font-display text-3xl font-extrabold text-volt">{{ $stats['surveyed_consumers'] }}</div>
                        <div class="text-[11px] text-white/45">Consumers done</div>
                    </div>
                    <div class="rounded-2xl border border-seas-200 bg-white p-4">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-seas-400">Pending</div>
                        <div class="mt-1 font-display text-3xl font-extrabold text-volt">{{ $stats['pending_consumers'] }}</div>
                        <div class="text-[11px] text-seas-400">Left to survey</div>
                    </div>
                    <div class="col-span-2 rounded-xl bg-seas-50 px-3 py-2 text-xs text-seas-500 flex justify-between">
                        <span>Poles: <strong class="text-seas-900">{{ $stats['total_poles'] }}</strong></span>
                        <span>Houses on poles: <strong class="text-seas-900">{{ $stats['total_houses'] }}</strong></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Search --}}
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-seas-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
            <input type="search" x-model="q" placeholder="Search Pole" class="seas-input !pl-11">
        </div>

        <div class="flex items-center justify-between">
            <h3 class="font-display font-extrabold text-seas-900">
                Pole List
                <span class="text-seas-400 font-semibold">(Total Poles: {{ $poles->count() }})</span>
            </h3>
        </div>

        {{-- Pole cards --}}
        <div class="space-y-3">
            @forelse($poles as $pole)
                @php
                    $pendingOnPole = max(0, (int) $pole->houses_connected - (int) $pole->surveyed_count);
                    $label = preg_replace('/\D+/', '', $pole->pole_no) ?: str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                    if (strlen($label) === 1) $label = '0'.$label;
                @endphp
                <div x-show="!q || '{{ strtolower($pole->pole_no) }}'.includes(q.toLowerCase())"
                     class="flex items-center gap-4 rounded-2xl border border-seas-100 bg-white p-4 shadow-sm">
                    <a href="{{ route('consumer.consumers', [$survey, $pole]) }}" class="flex min-w-0 flex-1 items-center gap-4 transition hover:opacity-90">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-seas-950 text-volt">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3M8 5h8l1.2 4.5H6.8L8 5zm-1.2 4.5.9 12.5h8.6l.9-12.5M10 22h4"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-display text-lg font-extrabold text-seas-900">{{ $pole->pole_no }}</span>
                                <span class="rounded bg-seas-100 px-1.5 py-0.5 text-[10px] font-bold text-seas-600">{{ $label }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-seas-400">
                                Source: {{ $pole->source_type === 'dtr' ? 'DTR' : 'Previous Pole' }}
                                · Houses: <strong class="text-seas-700">{{ $pole->houses_connected }}</strong>
                                · Surveyed: <strong class="text-volt">{{ $pole->surveyed_count }}</strong>
                                · Pending: <strong>{{ $pendingOnPole }}</strong>
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-volt" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                    </a>
                    @if(auth()->user()->isSuperAdmin())
                        <form method="POST" action="{{ route('admin.poles.destroy', $pole) }}" onsubmit="return confirm('Delete pole {{ $pole->pole_no }}?');">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-xl border border-volt/30 px-3 py-2 text-xs font-bold text-volt hover:bg-volt-soft">Delete</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-seas-200 py-12 text-center text-seas-400">
                    No poles yet — use <strong>+ Add Pole</strong> below to create the first one.
                </div>
            @endforelse
        </div>

        @if(auth()->user()->isFieldExecutive())
            {{-- Add Pole panel — Field Executive only (Super Admin cannot create) --}}
            <div class="seas-card overflow-hidden">
                <button type="button" @click="showAdd = !showAdd" class="flex w-full items-center justify-center gap-2 bg-seas-950 px-4 py-4 text-sm font-extrabold text-white hover:bg-seas-800">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-volt text-white text-lg leading-none">+</span>
                    Add Pole
                </button>
                <div x-show="showAdd" x-cloak class="border-t border-seas-100 p-5">
                    <p class="mb-3 text-xs text-seas-400">Poles are not in master data — they are added in the field. Capture how many houses / consumers are connected on each pole.</p>
                    <form method="POST" action="{{ route('consumer.poles.store', $survey) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-bold text-seas-500">Pole No *</label>
                            <input name="pole_no" required class="seas-input" placeholder="Pole-06" value="Pole-{{ str_pad($poles->count()+1, 2, '0', STR_PAD_LEFT) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-seas-500">Houses / consumers connected *</label>
                            <input type="number" min="0" name="houses_connected" required class="seas-input" value="0">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-seas-500">Power source *</label>
                            <select name="source_type" id="source_type" class="seas-input">
                                <option value="dtr">From DTR (direct)</option>
                                <option value="previous_pole">From Previous Pole</option>
                            </select>
                        </div>
                        <div id="prevWrap" class="hidden">
                            <label class="mb-1 block text-xs font-bold text-seas-500">Previous Pole</label>
                            <select name="previous_pole_id" class="seas-input">
                                <option value="">Select</option>
                                @foreach($poles as $p)
                                    <option value="{{ $p->id }}">{{ $p->pole_no }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <button class="seas-btn-primary w-full">Save Pole</button>
                        </div>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('consumer.finish', $survey) }}" onsubmit="return confirm('Finish consumer survey for this DTR? Energy audit for this DTR will be marked completed.')">
                @csrf
                <button class="seas-btn-secondary w-full">Finish DTR · Mark Consumer Survey Completed</button>
            </form>
        @endif
    </div>

    @push('scripts')
    <script>
        const st = document.getElementById('source_type');
        const wrap = document.getElementById('prevWrap');
        const toggle = () => wrap?.classList.toggle('hidden', st?.value !== 'previous_pole');
        st?.addEventListener('change', toggle); toggle();
    </script>
    @endpush
</x-app-layout>
