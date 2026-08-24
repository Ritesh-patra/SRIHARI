<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="seas-eyebrow">Pipeline</div>
            <h1 class="font-display text-xl font-bold text-seas-900 sm:text-2xl">DTR Status</h1>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-desktop 2xl:max-w-desktop-wide space-y-6 sm:space-y-6 lg:space-y-8 animate-fade-up">
        <div class="seas-pipeline">
            <a href="{{ route('dtr.status', ['status' => 'pending']) }}" class="{{ $status === 'pending' ? 'bg-volt-soft' : '' }}">
                <div class="font-display text-3xl font-extrabold text-volt">{{ $groups['pending'] }}</div>
                <div class="mt-1 text-xs font-bold uppercase tracking-wide text-seas-400">Pending</div>
            </a>
            <a href="{{ route('dtr.status', ['status' => 'rejected']) }}" class="{{ $status === 'rejected' ? 'bg-seas-50' : '' }}">
                <div class="font-display text-3xl font-extrabold text-seas-900">{{ $groups['rejected'] }}</div>
                <div class="mt-1 text-xs font-bold uppercase tracking-wide text-seas-400">Rejected</div>
            </a>
            <a href="{{ route('dtr.status', ['status' => 'approved']) }}" class="{{ $status === 'approved' ? 'bg-seas-50' : '' }}">
                <div class="font-display text-3xl font-extrabold text-seas-900">{{ $groups['approved'] }}</div>
                <div class="mt-1 text-xs font-bold uppercase tracking-wide text-seas-400">Approved</div>
            </a>
            <a href="{{ route('dtr.status', ['status' => 'completed']) }}" class="{{ $status === 'completed' ? 'bg-seas-50' : '' }}">
                <div class="font-display text-3xl font-bold text-seas-900">{{ $groups['completed'] }}</div>
                <div class="mt-1 text-xs font-bold uppercase tracking-wide text-seas-400">Completed</div>
            </a>
        </div>

        <div class="seas-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-seas-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="font-display font-bold text-seas-900">
                    @switch($status)
                        @case('pending') Pending for Approval @break
                        @case('rejected') Rejected @break
                        @case('approved') Approved DTRs @break
                        @case('completed') Consumer Survey Completed @break
                        @default All DTRs
                    @endswitch
                </h3>
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search DTR / Feeder…"
                           class="seas-input w-56 text-sm">
                    <button class="seas-btn-primary !py-2.5 !px-4 text-sm">Search</button>
                </form>
            </div>

            <div class="divide-y divide-seas-100">
                @forelse($surveys as $survey)
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between hover:bg-seas-50/70">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-seas-900">{{ $survey->dtr_code }}</span>
                                <span class="text-seas-700">{{ $survey->dtr_name }}</span>
                                <span class="seas-badge
                                    {{ $survey->displayStatus() === 'pending_for_approval' ? 'bg-volt-soft text-volt-deep' : '' }}
                                    {{ $survey->displayStatus() === 'rejected' ? 'bg-seas-950 text-white' : '' }}
                                    {{ $survey->displayStatus() === 'approved' ? 'bg-seas-100 text-seas-800' : '' }}
                                    {{ $survey->displayStatus() === 'consumer_survey_completed' ? 'bg-seas-100 text-seas-800' : '' }}
                                    {{ $survey->displayStatus() === 'draft' ? 'bg-seas-100 text-seas-600' : '' }}
                                ">{{ $survey->displayStatusLabel() }}</span>
                            </div>
                            <div class="mt-1 text-xs text-seas-400">
                                Feeder: {{ $survey->feeder_name }} · Surveyor: {{ $survey->surveyor?->name }} · {{ $survey->surveyed_at?->format('d M Y') }}
                            </div>
                            @if($survey->status === 'rejected' && $survey->review_remarks)
                                <div class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    Reason: {{ $survey->review_remarks }}
                                </div>
                            @endif
                            @if($survey->consumer_survey_completed_at)
                                <div class="mt-1 text-xs font-semibold text-volt-deep">
                                    Consumers saved: {{ $survey->consumerSurveys->count() }}
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            <a href="{{ route('surveys.show', $survey) }}" class="seas-btn-secondary !px-3 !py-2 text-xs">View</a>
                            @if($survey->status === 'rejected' && (auth()->user()->isSurveyor() || auth()->user()->isAdmin()) && $survey->surveyor_id === auth()->id())
                                <a href="{{ route('surveys.edit', $survey) }}" class="seas-btn !px-3 !py-2 text-xs bg-rose-600 text-white hover:bg-rose-500">Edit & Resubmit</a>
                            @endif
                            @if($survey->isApprovedForConsumerSurvey())
                                <a href="{{ route('consumer.poles', $survey) }}" class="seas-btn-success !px-3 !py-2 text-xs">Start Consumer Survey</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center text-seas-400">No DTRs in this status.</div>
                @endforelse
            </div>
            <div class="border-t border-seas-100 px-5 py-3">{{ $surveys->links() }}</div>
        </div>
    </div>
</x-app-layout>
