@php
    $isEdit = isset($survey);
    $action = $isEdit ? route('surveys.update', $survey) : route('surveys.store');
    $method = $isEdit ? 'PUT' : 'POST';
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6" id="surveyForm">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- 1. Survey Information --}}
    <section class="seas-card-pad">
        <h3 class="font-display text-lg font-bold text-seas-900 mb-4">1. Survey Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Survey Date & Time</label>
                <input type="text" disabled class="seas-input bg-slate-50"
                       value="{{ $isEdit ? $survey->surveyed_at->format('d-M-Y H:i:s') : now()->format('d-M-Y H:i:s') }}">
                <p class="text-xs text-slate-400 mt-1">Auto capture</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Surveyor Name</label>
                <input type="text" disabled class="seas-input bg-slate-50" value="{{ auth()->user()->name }}">
                <p class="text-xs text-slate-400 mt-1">Auto capture</p>
            </div>
        </div>
    </section>

    {{-- 2. Location Details --}}
    <section class="seas-card-pad">
        <h3 class="font-display text-lg font-bold text-seas-900 mb-4">2. Location Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Region *</label>
                <select name="region_id" id="region_id" required class="seas-input">
                    <option value="">Select Region</option>
                    @foreach($regions as $region)
                        <option value="{{ $region->id }}" @selected(old('region_id', $survey->region_id ?? '') == $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Circle *</label>
                <select name="circle_id" id="circle_id" required class="seas-input">
                    <option value="">Select Circle</option>
                    @isset($circles)
                        @foreach($circles as $item)
                            <option value="{{ $item->id }}" @selected(old('circle_id', $survey->circle_id ?? '') == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Division *</label>
                <select name="division_id" id="division_id" required class="seas-input">
                    <option value="">Select Division</option>
                    @isset($divisions)
                        @foreach($divisions as $item)
                            <option value="{{ $item->id }}" @selected(old('division_id', $survey->division_id ?? '') == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Zone / DC *</label>
                <select name="zone_id" id="zone_id" required class="seas-input">
                    <option value="">Select Zone</option>
                    @isset($zones)
                        @foreach($zones as $item)
                            <option value="{{ $item->id }}" @selected(old('zone_id', $survey->zone_id ?? '') == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Substation *</label>
                <select name="substation_id" id="substation_id" required class="seas-input">
                    <option value="">Select Substation</option>
                    @isset($substations)
                        @foreach($substations as $item)
                            <option value="{{ $item->id }}" @selected(old('substation_id', $survey->substation_id ?? '') == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Feeder *</label>
                <select name="feeder_id" id="feeder_id" required class="seas-input">
                    <option value="">Select Feeder</option>
                    @isset($feeders)
                        @foreach($feeders as $item)
                            <option value="{{ $item->id }}" data-code="{{ $item->code }}" @selected(old('feeder_id', $survey->feeder_id ?? '') == $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                        @endforeach
                    @endisset
                </select>
            </div>
        </div>
    </section>

    {{-- 3. DTR Information --}}
    <section class="seas-card-pad">
        <h3 class="font-display text-lg font-bold text-seas-900 mb-4">3. DTR Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-600 mb-1">DTR *</label>
                <select name="dtr_id" id="dtr_id" required class="seas-input">
                    <option value="">Select DTR</option>
                    @isset($dtrs)
                        @foreach($dtrs as $item)
                            <option value="{{ $item->id }}" data-capacity="{{ $item->capacity_kva }}" @selected(old('dtr_id', $survey->dtr_id ?? '') == $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Latitude (Auto)</label>
                <input type="text" name="latitude" id="latitude" class="w-full rounded-xl border-slate-200 bg-slate-50" readonly value="{{ old('latitude', $survey->latitude ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Longitude (Auto)</label>
                <input type="text" name="longitude" id="longitude" class="w-full rounded-xl border-slate-200 bg-slate-50" readonly value="{{ old('longitude', $survey->longitude ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">GPS Accuracy (Auto)</label>
                <input type="text" name="gps_accuracy" id="gps_accuracy" class="w-full rounded-xl border-slate-200 bg-slate-50" readonly value="{{ old('gps_accuracy', $survey->gps_accuracy ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">DTR Capacity (kVA)</label>
                <input type="number" name="dtr_capacity_kva" id="dtr_capacity_kva" class="seas-input" value="{{ old('dtr_capacity_kva', $survey->dtr_capacity_kva ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">DTR Condition *</label>
                <select name="dtr_condition" required class="seas-input">
                    <option value="">Select</option>
                    @foreach($dtrConditions as $c)
                        <option value="{{ $c }}" @selected(old('dtr_condition', $survey->dtr_condition ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p id="gpsStatus" class="text-sm text-slate-500 mt-3">Capturing GPS…</p>
    </section>

    {{-- 4. Smart Meter --}}
    <section class="seas-card-pad">
        <h3 class="font-display text-lg font-bold text-seas-900 mb-4">4. Smart Meter Information</h3>
        <label class="block text-sm font-semibold text-slate-600 mb-1">Smart Meter Status *</label>
        <select name="smart_meter_status" id="smart_meter_status" required class="seas-input max-w-md">
            <option value="">Select</option>
            @foreach($smartMeterStatuses as $s)
                <option value="{{ $s }}" @selected(old('smart_meter_status', $survey->smart_meter_status ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </section>

    {{-- 5. Old Meter --}}
    <section id="oldMeterSection" class="seas-card-pad border-rose-200 bg-rose-50/80 hidden">
        <h3 class="font-display text-lg font-bold text-rose-800 mb-4">5. Old Meter Details</h3>
        <p class="text-sm text-rose-600 mb-4">Visible only when Smart Meter Status = Not Installed</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Old Meter Condition *</label>
                <select name="old_meter_condition" id="old_meter_condition" class="seas-input">
                    <option value="">Select</option>
                    @foreach($oldMeterConditions as $c)
                        <option value="{{ $c }}" @selected(old('old_meter_condition', $survey->old_meter_condition ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Old MSN *</label>
                <input type="text" name="old_msn" class="seas-input" value="{{ old('old_msn', $survey->old_msn ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Old Meter Make *</label>
                <input type="text" name="old_meter_make" class="seas-input" value="{{ old('old_meter_make', $survey->old_meter_make ?? '') }}">
            </div>
        </div>
    </section>

    {{-- 6. New Meter --}}
    <section class="seas-card-pad">
        <h3 class="font-display text-lg font-bold text-seas-900 mb-4">6. New Smart Meter Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">New MSN</label>
                <input type="text" name="new_msn" class="seas-input" value="{{ old('new_msn', $survey->new_msn ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">New Meter Make</label>
                <select name="new_meter_make" class="seas-input">
                    <option value="">Select</option>
                    @foreach($newMeterMakes as $m)
                        <option value="{{ $m }}" @selected(old('new_meter_make', $survey->new_meter_make ?? '') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">New Meter External CT Ratio</label>
                <input type="text" name="new_meter_ct_ratio" class="seas-input" placeholder="e.g. 500/5" value="{{ old('new_meter_ct_ratio', $survey->new_meter_ct_ratio ?? '') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">New Meter MF</label>
                <input type="text" name="new_meter_mf" class="seas-input" value="{{ old('new_meter_mf', $survey->new_meter_mf ?? '') }}">
            </div>
        </div>
    </section>

    {{-- 7. Photos --}}
    <section class="seas-card-pad">
        <h3 class="text-lg font-semibold text-slate-800 mb-1">7. Photo Capture</h3>
        <p class="text-sm text-rose-600 mb-4">Mandatory for submission</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">DTR Overall Photo *</label>
                <input type="file" name="dtr_overall_photo" accept="image/*" capture="environment" class="w-full text-sm">
                @if($isEdit && $survey->dtr_overall_photo)
                    <img src="{{ asset('storage/'.$survey->dtr_overall_photo) }}" class="mt-2 h-28 rounded-xl object-cover border" alt="DTR photo">
                @endif
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">Smart Meter Photo *</label>
                <input type="file" name="smart_meter_photo" accept="image/*" capture="environment" class="w-full text-sm">
                @if($isEdit && $survey->smart_meter_photo)
                    <img src="{{ asset('storage/'.$survey->smart_meter_photo) }}" class="mt-2 h-28 rounded-xl object-cover border" alt="Meter photo">
                @endif
            </div>
        </div>
    </section>

    {{-- 8. Observation --}}
    <section class="seas-card-pad">
        <h3 class="font-display text-lg font-bold text-seas-900 mb-4">8. Observation (Remarks)</h3>
        <textarea name="observation" rows="4" class="seas-input" placeholder="Example: DTR name plate damaged. Oil leakage observed. No abnormality found.">{{ old('observation', $survey->observation ?? '') }}</textarea>
    </section>

    {{-- 9. Submit --}}
    <div class="sticky bottom-4 z-20 flex flex-col sm:flex-row gap-3 justify-end rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur">
        <button type="submit" name="action" value="draft" class="seas-btn-secondary">
            Save Draft
        </button>
        <button type="submit" name="action" value="submit" class="seas-btn-primary">
            Submit for Manager Approval
        </button>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const fillSelect = (el, items, labelFn = i => i.name) => {
        el.innerHTML = '<option value="">Select</option>';
        items.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = labelFn(i);
            if (i.code) opt.dataset.code = i.code;
            if (i.capacity_kva) opt.dataset.capacity = i.capacity_kva;
            el.appendChild(opt);
        });
    };

    const load = async (url, params) => {
        const qs = new URLSearchParams(params).toString();
        const res = await fetch(`${url}?${qs}`, { headers: { 'Accept': 'application/json' }});
        return res.json();
    };

    const region = document.getElementById('region_id');
    const circle = document.getElementById('circle_id');
    const division = document.getElementById('division_id');
    const zone = document.getElementById('zone_id');
    const substation = document.getElementById('substation_id');
    const feeder = document.getElementById('feeder_id');
    const dtr = document.getElementById('dtr_id');
    const capacity = document.getElementById('dtr_capacity_kva');
    const status = document.getElementById('smart_meter_status');
    const oldSec = document.getElementById('oldMeterSection');

    const toggleOld = () => {
        const show = status.value === 'Not Installed';
        oldSec.classList.toggle('hidden', !show);
    };
    status.addEventListener('change', toggleOld);
    toggleOld();

    region.addEventListener('change', async () => {
        fillSelect(circle, await load('/api/hierarchy/circles', { region_id: region.value }));
        fillSelect(division, []); fillSelect(zone, []); fillSelect(substation, []); fillSelect(feeder, []); fillSelect(dtr, []);
    });
    circle.addEventListener('change', async () => {
        fillSelect(division, await load('/api/hierarchy/divisions', { circle_id: circle.value }));
        fillSelect(zone, []); fillSelect(substation, []); fillSelect(feeder, []); fillSelect(dtr, []);
    });
    division.addEventListener('change', async () => {
        fillSelect(zone, await load('/api/hierarchy/zones', { division_id: division.value }));
        fillSelect(substation, []); fillSelect(feeder, []); fillSelect(dtr, []);
    });
    zone.addEventListener('change', async () => {
        fillSelect(substation, await load('/api/hierarchy/substations', { zone_id: zone.value }));
        fillSelect(feeder, []); fillSelect(dtr, []);
    });
    substation.addEventListener('change', async () => {
        fillSelect(feeder, await load('/api/hierarchy/feeders', { substation_id: substation.value }), i => `${i.name} (${i.code})`);
        fillSelect(dtr, []);
    });
    feeder.addEventListener('change', async () => {
        fillSelect(dtr, await load('/api/hierarchy/dtrs', { feeder_id: feeder.value }), i => `${i.name} (${i.code})`);
    });
    dtr.addEventListener('change', () => {
        const opt = dtr.selectedOptions[0];
        if (opt?.dataset.capacity) capacity.value = opt.dataset.capacity;
    });

    const gpsStatus = document.getElementById('gpsStatus');
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.getElementById('latitude').value = pos.coords.latitude.toFixed(7);
                document.getElementById('longitude').value = pos.coords.longitude.toFixed(7);
                document.getElementById('gps_accuracy').value = pos.coords.accuracy?.toFixed(2) ?? '';
                gpsStatus.textContent = 'GPS captured successfully.';
                gpsStatus.className = 'text-sm text-emerald-600 mt-3';
            },
            () => {
                gpsStatus.textContent = 'GPS unavailable — you can still submit; accuracy may be empty.';
                gpsStatus.className = 'text-sm text-amber-600 mt-3';
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        gpsStatus.textContent = 'Geolocation not supported in this browser.';
    }
})();
</script>
@endpush
