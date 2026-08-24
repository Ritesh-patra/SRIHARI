<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6" x-data="{
        selected: [],
        action: 'approve',
        remark: '',
        photoOpen: false,
        photoSlides: [],
        photoIndex: 0,
        openPhotos(slides) {
            this.photoSlides = slides || [];
            this.photoIndex = 0;
            this.photoOpen = this.photoSlides.length > 0;
        },
        closePhotos() {
            this.photoOpen = false;
            this.photoSlides = [];
            this.photoIndex = 0;
        },
        nextPhoto() {
            if (!this.photoSlides.length) return;
            this.photoIndex = (this.photoIndex + 1) % this.photoSlides.length;
        },
        prevPhoto() {
            if (!this.photoSlides.length) return;
            this.photoIndex = (this.photoIndex - 1 + this.photoSlides.length) % this.photoSlides.length;
        },
        toggleAll(e) {
            const boxes = [...document.querySelectorAll('input.row-check')];
            this.selected = e.target.checked ? boxes.map(b => b.value) : [];
            boxes.forEach(b => b.checked = e.target.checked);
        },
        sync() {
            this.selected = [...document.querySelectorAll('input.row-check:checked')].map(b => b.value);
        }
    }"
    @keydown.escape.window="if (photoOpen) closePhotos()"
    @keydown.arrow-right.window="if (photoOpen) nextPhoto()"
    @keydown.arrow-left.window="if (photoOpen) prevPhoto()">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10">
                <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">Action</span>
                <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Consumer Survey Approval</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">Filter, review meter photos, approve or reject (with remark). Export matches the Sample File Excel format.</p>
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <form method="GET" action="{{ route('consumer-approval.index') }}" class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Region</label>
                    <select name="region_id" class="al-input" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}" @selected((string)$filters['region_id'] === (string)$r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Circle</label>
                    <select name="circle_id" class="al-input">
                        <option value="">All</option>
                        @foreach($circles as $c)
                            <option value="{{ $c->id }}" @selected((string)$filters['circle_id'] === (string)$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Division</label>
                    <select name="division_id" class="al-input">
                        <option value="">All</option>
                        @foreach($divisions as $d)
                            <option value="{{ $d->id }}" @selected((string)$filters['division_id'] === (string)$d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Zone / DC</label>
                    <select name="zone_id" class="al-input">
                        <option value="">All</option>
                        @foreach($zones as $z)
                            <option value="{{ $z->id }}" @selected((string)$filters['zone_id'] === (string)$z->id)>{{ $z->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Meter Phase</label>
                    <select name="phase" class="al-input">
                        <option value="all" @selected($filters['phase'] === 'all')>All</option>
                        <option value="1PH" @selected($filters['phase'] === '1PH')>1PH</option>
                        <option value="3PH" @selected($filters['phase'] === '3PH')>3PH</option>
                        <option value="3PH 4CT" @selected($filters['phase'] === '3PH 4CT')>3PH 4CT</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">From Date</label>
                    <input type="date" name="from" class="al-input" value="{{ $filters['from'] }}">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">To Date</label>
                    <input type="date" name="to" class="al-input" value="{{ $filters['to'] }}">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">IVRS Number</label>
                    <input type="text" name="ivrs" class="al-input" value="{{ $filters['ivrs'] }}" placeholder="IVRS">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Status</label>
                    <select name="status" class="al-input">
                        <option value="pending_approval" @selected($filters['status'] === 'pending_approval')>Pending for Approval</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">DTR Code</label>
                    <input type="text" name="dtr_code" class="al-input" value="{{ $filters['dtr_code'] }}" placeholder="DTR code">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Surveyor</label>
                    <select name="surveyor_id" class="al-input">
                        <option value="">All</option>
                        @foreach($surveyors as $s)
                            <option value="{{ $s->id }}" @selected((string)$filters['surveyor_id'] === (string)$s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2 md:col-span-3 xl:col-span-4">
                    <button name="view" value="1" class="al-btn al-btn-primary">View</button>
                    <button name="download" value="1" class="al-btn al-btn-ink">Download</button>
                </div>
            </form>
        </section>

        @if($viewed)
            <section class="al-panel overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3" style="border-color: var(--al-border)">
                    <label class="text-[11px] font-bold uppercase tracking-wide text-muted">Action</label>
                    <select x-model="action" class="al-input !w-52 !py-1.5 text-sm">
                        <option value="approve">Approve</option>
                        <option value="reject">Reject (remark)</option>
                        <option value="delete">Delete permanently</option>
                    </select>
                    <template x-if="action === 'reject'">
                        <input type="text" x-model="remark" class="al-input !max-w-xs text-sm" placeholder="Reject remark (required)">
                    </template>
                    <button type="button"
                        class="al-btn al-btn-primary !py-2 text-xs"
                        :class="action === 'delete' ? '!bg-rose-600' : ''"
                        :disabled="selected.length === 0"
                        @click="
                            if (selected.length === 0) return;
                            if (action === 'reject' && !remark.trim()) { alert('Remark is required to reject.'); return; }
                            if (action === 'delete' && !confirm('Permanently delete ' + selected.length + ' survey(s)? This cannot be undone.')) return;
                            $refs.bulkForm.querySelector('[name=action]').value = action;
                            $refs.bulkForm.querySelector('[name=remark]').value = remark;
                            [...$refs.bulkForm.querySelectorAll('input[name=&quot;ids[]&quot;]')].forEach(n => n.remove());
                            selected.forEach(id => {
                                const i = document.createElement('input');
                                i.type = 'hidden'; i.name = 'ids[]'; i.value = id;
                                $refs.bulkForm.appendChild(i);
                            });
                            $refs.bulkForm.submit();
                        ">
                        <span x-text="action === 'approve' ? 'Approve' : (action === 'reject' ? 'Reject' : 'Delete')"></span>
                        (<span x-text="selected.length"></span>)
                    </button>
                    <span class="text-xs text-muted" x-show="selected.length">Selected: <span x-text="selected.length"></span></span>
                </div>

                <form x-ref="bulkForm" method="POST" action="{{ route('consumer-approval.bulk') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="remark" value="">
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-seas-950 text-white">
                            <tr>
                                <th class="px-3 py-2"><input type="checkbox" @change="toggleAll($event)"></th>
                                <th class="px-3 py-2">Photo</th>
                                <th class="px-3 py-2">Surveyor</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">IVRS</th>
                                <th class="px-3 py-2">Scope</th>
                                <th class="px-3 py-2">New MSN</th>
                                <th class="px-3 py-2">Make</th>
                                <th class="px-3 py-2">Phase</th>
                                <th class="px-3 py-2">Consumer</th>
                                <th class="px-3 py-2">Pole</th>
                                <th class="px-3 py-2">DTR</th>
                                <th class="px-3 py-2">Feeder</th>
                                <th class="px-3 py-2">Zone</th>
                                <th class="px-3 py-2">Division</th>
                                <th class="px-3 py-2">Circle</th>
                                <th class="px-3 py-2">Lat / Lng</th>
                                <th class="px-3 py-2">Remark</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--al-border)">
                            @forelse($surveys as $row)
                                @php $api = \App\Support\ConsumerSurveyApproval::apiRow($row); @endphp
                                <tr class="hover:bg-black/[0.02]">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="row-check" value="{{ $row->id }}" @change="sync()">
                                    </td>
                                    <td class="px-3 py-2">
                                        @php
                                            $photoSlides = array_values(array_filter([
                                                ! empty($api['meter_photo_url']) ? ['url' => $api['meter_photo_url'], 'label' => 'Meter photo'] : null,
                                                ! empty($api['premise_photo_url']) ? ['url' => $api['premise_photo_url'], 'label' => 'Premise photo'] : null,
                                            ]));
                                        @endphp
                                        @if(count($photoSlides))
                                            <button type="button"
                                                    class="text-xs font-bold text-volt underline-offset-2 hover:underline"
                                                    @click="openPhotos({{ \Illuminate\Support\Js::from($photoSlides) }})">
                                                View
                                            </button>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-semibold text-theme">{{ $api['surveyor']['name'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase
                                            {{ $row->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($row->status === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ str_replace('_', ' ', $row->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-muted">{{ ($row->surveyed_at ?? $row->created_at)?->format('d M Y, H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 font-semibold text-volt">{{ $api['ivrs'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['verification_status'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['msn'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['meter_make'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['phase'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['consumer_name'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['pole_no'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $api['dtr_name'] }} ({{ $api['dtr_code'] }})</td>
                                    <td class="px-3 py-2">{{ $api['feeder_name'] }}</td>
                                    <td class="px-3 py-2">{{ $api['zone_name'] }}</td>
                                    <td class="px-3 py-2">{{ $api['division_name'] }}</td>
                                    <td class="px-3 py-2">{{ $api['circle_name'] }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $api['latitude'] }} / {{ $api['longitude'] }}</td>
                                    <td class="px-3 py-2 max-w-[160px] truncate" title="{{ $api['observation'] ?? $api['review_remarks'] }}">{{ $api['observation'] ?: ($api['review_remarks'] ?: '—') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="19" class="px-5 py-10 text-center text-muted">No consumer surveys match these filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($surveys instanceof \Illuminate\Contracts\Pagination\Paginator && $surveys->hasPages())
                    <div class="border-t px-4 py-3" style="border-color: var(--al-border)">{{ $surveys->links() }}</div>
                @endif
            </section>
        @else
            <section class="al-panel px-5 py-10 text-center text-sm text-muted">
                Set filters and click <strong class="text-theme">View</strong> to load surveys.
            </section>
        @endif

        {{-- Photo lightbox: image URL loads only after View is clicked --}}
        <div x-show="photoOpen"
             x-cloak
             class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6"
             role="dialog"
             aria-modal="true"
             aria-label="Survey photo">
            <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="closePhotos()"></div>
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-white" x-text="photoSlides[photoIndex]?.label || 'Photo'"></div>
                        <div class="truncate text-xs text-white/60" x-show="photoSlides.length > 1">
                            <span x-text="photoIndex + 1"></span> / <span x-text="photoSlides.length"></span>
                        </div>
                    </div>
                    <button type="button"
                            class="al-btn al-btn-primary shrink-0 px-3 py-2"
                            @click="closePhotos()"
                            aria-label="Close lightbox">
                        Close
                    </button>
                </div>
                <div class="relative flex min-h-0 flex-1 items-center justify-center rounded-xl bg-black/40 p-2 sm:p-4">
                    <template x-if="photoOpen && photoSlides[photoIndex]">
                        <img :src="photoSlides[photoIndex].url"
                             :alt="photoSlides[photoIndex].label || 'Photo'"
                             class="max-h-[78vh] w-auto max-w-full object-contain">
                    </template>
                    <template x-if="photoSlides.length > 1">
                        <div>
                            <button type="button"
                                    class="absolute left-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25"
                                    @click.stop="prevPhoto()"
                                    aria-label="Previous photo">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                            </button>
                            <button type="button"
                                    class="absolute right-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25"
                                    @click.stop="nextPhoto()"
                                    aria-label="Next photo">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
