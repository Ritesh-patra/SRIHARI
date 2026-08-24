<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="seas-eyebrow">Work</div>
            <h1 class="font-display text-xl font-extrabold text-seas-900">Assignments</h1>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-6 sm:space-y-6 lg:space-y-8 animate-fade-up">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif

        @if(auth()->user()->canApproveSurveys() || auth()->user()->isAdmin())
            <form method="POST" action="{{ route('assignments.store') }}" class="seas-card-pad grid gap-3 md:grid-cols-2">
                @csrf
                <h3 class="md:col-span-2 font-display font-extrabold">Assign work to Field Executive</h3>
                <select name="assigned_to" required class="seas-input">
                    <option value="">Select FE</option>
                    @foreach($fieldExecs as $fe)
                        <option value="{{ $fe->id }}">{{ $fe->name }}</option>
                    @endforeach
                </select>
                <select name="feeder_id" class="seas-input">
                    <option value="">Feeder (optional)</option>
                    @foreach($feeders as $f)
                        <option value="{{ $f->id }}">{{ $f->code }} — {{ $f->name }}</option>
                    @endforeach
                </select>
                <select name="dtr_id" class="seas-input">
                    <option value="">DTR (optional)</option>
                    @foreach($dtrs as $d)
                        <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="work_date" required class="seas-input" value="{{ now()->toDateString() }}">
                <input name="notes" class="seas-input" placeholder="Notes">
                <button class="seas-btn-primary md:col-span-2">Assign</button>
            </form>
        @endif

        <div class="seas-card overflow-hidden">
            <div class="divide-y divide-seas-100">
                @forelse($assignments as $a)
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-bold text-seas-900">{{ $a->assignee?->name }}</div>
                            <div class="text-xs text-seas-400">
                                {{ $a->dtr?->name ?? $a->feeder?->name ?? '—' }} · by {{ $a->assigner?->name }}
                                @if($a->work_date) · work date {{ $a->work_date->format('d M Y') }} @endif
                            </div>
                            @if($a->notes)<div class="mt-1 text-sm text-seas-600">{{ $a->notes }}</div>@endif
                        </div>
                        <form method="POST" action="{{ route('assignments.status', $a) }}" class="flex gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="seas-input !py-2 text-sm">
                                @foreach(['open','in_progress','done','closed'] as $st)
                                    <option value="{{ $st }}" @selected($a->status === $st)>{{ $st }}</option>
                                @endforeach
                            </select>
                            <button class="seas-btn-secondary !py-2 text-xs">Update</button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center text-seas-400">No assignments.</div>
                @endforelse
            </div>
            <div class="border-t border-seas-100 px-5 py-3">{{ $assignments->links() }}</div>
        </div>
    </div>
</x-app-layout>
