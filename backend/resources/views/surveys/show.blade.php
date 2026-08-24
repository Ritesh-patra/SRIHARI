<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">DTR Survey #{{ $survey->id }}</h2>
                <p class="text-sm text-slate-500">{{ $survey->dtr_name }} · {{ $survey->dtr_code }}</p>
            </div>
            <div class="flex gap-2">
                @if((auth()->user()->isSurveyor() || auth()->user()->isAdmin()) && $survey->isEditable() && $survey->surveyor_id === auth()->id())
                    <a href="{{ route('surveys.edit', $survey) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Edit & Resubmit</a>
                @endif
                <a href="{{ route('reports.print', $survey) }}" target="_blank" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Print</a>
                @if($survey->isApprovedForConsumerSurvey())
                    <a href="{{ route('consumer.poles', $survey) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-emerald-600/20">Start Consumer Survey</a>
                @endif
                <a href="{{ route('dtr.status') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6 px-4 sm:px-0">
            @if(session('success'))
                <div class="rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-3">{{ session('success') }}</div>
            @endif

            @php
                $colors = [
                    'draft' => 'bg-slate-100 text-slate-700',
                    'pending_approval' => 'bg-amber-100 text-amber-800',
                    'approved' => 'bg-emerald-100 text-emerald-800',
                    'rejected' => 'bg-rose-100 text-rose-800',
                ];
            @endphp
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $colors[$survey->status] ?? '' }}">
                    {{ str_replace('_', ' ', ucfirst($survey->status)) }}
                </span>
                @if($survey->review_remarks)
                    <span class="text-sm text-slate-600">Review: {{ $survey->review_remarks }}</span>
                @endif
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2 text-sm">
                    <h3 class="font-semibold text-slate-800 mb-2">Survey Information</h3>
                    <div class="flex justify-between"><span class="text-slate-500">Date & Time</span><span>{{ $survey->surveyed_at?->format('d-M-Y H:i:s') }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Surveyor</span><span>{{ $survey->surveyor?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Supervisor</span><span>{{ $survey->supervisor?->name ?? '—' }}</span></div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2 text-sm">
                    <h3 class="font-semibold text-slate-800 mb-2">Location</h3>
                    <div class="flex justify-between"><span class="text-slate-500">Region</span><span>{{ $survey->region?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Circle</span><span>{{ $survey->circle?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Division</span><span>{{ $survey->division?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Zone/DC</span><span>{{ $survey->zone?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Substation</span><span>{{ $survey->substation?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Feeder</span><span>{{ $survey->feeder_name }} ({{ $survey->feeder_code }})</span></div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2 text-sm">
                    <h3 class="font-semibold text-slate-800 mb-2">DTR Information</h3>
                    <div class="flex justify-between"><span class="text-slate-500">DTR</span><span>{{ $survey->dtr_name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Code</span><span>{{ $survey->dtr_code }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Capacity</span><span>{{ $survey->dtr_capacity_kva }} kVA</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Condition</span><span>{{ $survey->dtr_condition }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">GPS</span><span>{{ $survey->latitude }}, {{ $survey->longitude }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Accuracy</span><span>{{ $survey->gps_accuracy }} m</span></div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2 text-sm">
                    <h3 class="font-semibold text-slate-800 mb-2">Meter Details</h3>
                    <div class="flex justify-between"><span class="text-slate-500">Smart Meter Status</span><span>{{ $survey->smart_meter_status }}</span></div>
                    @if($survey->smart_meter_status === 'Not Installed')
                        <div class="flex justify-between"><span class="text-slate-500">Old Condition</span><span>{{ $survey->old_meter_condition }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Old MSN</span><span>{{ $survey->old_msn }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Old Make</span><span>{{ $survey->old_meter_make }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-slate-500">New MSN</span><span>{{ $survey->new_msn ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">New Make</span><span>{{ $survey->new_meter_make ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">CT Ratio</span><span>{{ $survey->new_meter_ct_ratio ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">MF</span><span>{{ $survey->new_meter_mf ?? '—' }}</span></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-semibold text-slate-800 mb-3">Photos</h3>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-slate-500 mb-2">DTR Overall Photo</div>
                        @if($survey->dtr_overall_photo)
                            <img src="{{ asset('storage/'.$survey->dtr_overall_photo) }}" class="rounded-xl border w-full max-h-64 object-cover" alt="DTR">
                        @else
                            <div class="text-slate-400 text-sm">Not uploaded</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm text-slate-500 mb-2">Smart Meter Photo</div>
                        @if($survey->smart_meter_photo)
                            <img src="{{ asset('storage/'.$survey->smart_meter_photo) }}" class="rounded-xl border w-full max-h-64 object-cover" alt="Meter">
                        @else
                            <div class="text-slate-400 text-sm">Not uploaded</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-semibold text-slate-800 mb-2">Observation (Remarks)</h3>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $survey->observation ?: '—' }}</p>
            </div>

            @if($survey->isApprovedForConsumerSurvey())
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                    <div class="font-display text-lg font-bold">This DTR is approved</div>
                    <p class="mt-1 text-sm">You can start Consumer Survey — poles, houses connected & phone capture.</p>
                    <a href="{{ route('consumer.poles', $survey) }}" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Start Consumer Survey →</a>
                </div>
            @endif

            @if($survey->status === 'rejected')
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                    <div class="font-display text-lg font-bold">Rejected</div>
                    <p class="mt-1 text-sm">{{ $survey->review_remarks }}</p>
                    @if((auth()->user()->isFieldExecutive() || auth()->user()->isAdmin()) && $survey->surveyor_id === auth()->id())
                        <a href="{{ route('surveys.edit', $survey) }}" class="mt-4 inline-flex rounded-xl bg-volt px-4 py-2.5 text-sm font-bold text-white">Edit & Resubmit →</a>
                    @endif
                </div>
            @endif

            @if(!empty($canApprove) && $canApprove)
                <div class="bg-white rounded-2xl border border-seas-200 p-5 shadow-sm space-y-4">
                    <h3 class="font-semibold text-seas-900">Manager Review</h3>
                    <form method="POST" action="{{ route('surveys.approve', $survey) }}" class="space-y-3">
                        @csrf
                        <textarea name="review_remarks" rows="2" class="seas-input" placeholder="Optional approval remarks"></textarea>
                        <button class="seas-btn-success">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('surveys.reject', $survey) }}" class="space-y-3">
                        @csrf
                        <textarea name="review_remarks" rows="2" required class="seas-input" placeholder="Rejection reason (required)"></textarea>
                        <button class="seas-btn bg-seas-950 text-white">Reject</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
