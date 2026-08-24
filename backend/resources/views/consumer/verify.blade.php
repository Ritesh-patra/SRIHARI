<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800">Consumer Verification</h2>
            <p class="text-sm text-slate-500">{{ $survey->dtr_name }} · {{ $pole->pole_no }} · Houses: {{ $pole->houses_connected }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 px-4 sm:px-0">
            @if($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
                    <ul class="list-disc ms-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('consumer.save', [$survey, $pole]) }}" enctype="multipart/form-data" class="bg-white rounded-2xl border p-6 space-y-4 shadow-sm">
                @csrf
                @if($consumer)
                    <input type="hidden" name="consumer_id" value="{{ $consumer->id }}">
                @endif

                <div>
                    <label class="text-sm font-medium text-slate-600">Consumer Name</label>
                    <input name="consumer_name" class="w-full rounded-xl border-slate-200" value="{{ old('consumer_name', $consumer->name ?? '') }}">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-600">Phone *</label>
                    <input name="phone" required class="w-full rounded-xl border-slate-200" placeholder="10-digit mobile" value="{{ old('phone', $consumer->phone ?? '') }}">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium text-slate-600">IVRS</label>
                        <input name="ivrs" class="w-full rounded-xl border-slate-200" value="{{ old('ivrs', $consumer->ivrs ?? '') }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600">MSN</label>
                        <input name="msn" class="w-full rounded-xl border-slate-200" value="{{ old('msn', $consumer->msn ?? '') }}">
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-600">Houses connected on this pole</label>
                    <input type="number" min="0" name="update_houses_connected" class="w-full rounded-xl border-slate-200" value="{{ old('update_houses_connected', $pole->houses_connected) }}">
                    <p class="text-xs text-slate-400 mt-1">Update how many houses are connected on this pole.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium text-slate-600">Latitude (auto)</label>
                        <input name="latitude" id="latitude" readonly class="w-full rounded-xl border-slate-200 bg-slate-50">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600">Longitude (auto)</label>
                        <input name="longitude" id="longitude" readonly class="w-full rounded-xl border-slate-200 bg-slate-50">
                    </div>
                </div>
                <input type="hidden" name="gps_accuracy" id="gps_accuracy">

                <div>
                    <label class="text-sm font-medium text-slate-600">Meter Photo</label>
                    <input type="file" name="meter_photo" accept="image/*" capture="environment" class="w-full text-sm">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-600">Survey flag</label>
                    <select name="survey_flag" class="w-full rounded-xl border-slate-200">
                        <option value="">Normal / Verified</option>
                        <option value="new">New consumer</option>
                        <option value="not_accessible">Not accessible</option>
                        <option value="pdc">PDC</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-600">Observation</label>
                    <textarea name="observation" rows="3" class="w-full rounded-xl border-slate-200" placeholder="Optional remarks"></textarea>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('consumer.consumers', [$survey, $pole]) }}" class="flex-1 text-center px-4 py-3 rounded-xl border font-semibold">Cancel</a>
                    <button class="flex-1 px-4 py-3 rounded-xl bg-emerald-600 text-white font-semibold">Save Consumer</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                document.getElementById('latitude').value = pos.coords.latitude.toFixed(7);
                document.getElementById('longitude').value = pos.coords.longitude.toFixed(7);
                document.getElementById('gps_accuracy').value = pos.coords.accuracy?.toFixed(2) ?? '';
            });
        }
    </script>
    @endpush
</x-app-layout>
