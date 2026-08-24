<x-app-layout>
    @php
        $colors = [
            'draft' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
            'pending_approval' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
            'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
            'rejected' => 'bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-200',
        ];

        $gallery = [];
        foreach ([
            ['path' => $survey->substation_photo, 'label' => 'Substation Photo'],
            ['path' => $survey->meter_photo, 'label' => 'Meter Photo'],
            ['path' => $survey->nameplate_photo, 'label' => 'Nameplate Photo'],
            ['path' => $survey->sld_photo, 'label' => 'SLD Photo'],
        ] as $item) {
            if (! empty($item['path'])) {
                $gallery[] = [
                    'url' => \App\Support\SurveyPhotoStorage::url($item['path']),
                    'label' => $item['label'],
                ];
            }
        }
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-5 sm:space-y-6"
         x-data="{
            photoOpen: false,
            photoSlides: [],
            photoIndex: 0,
            openPhotos(slides, index) {
                this.photoSlides = slides || [];
                this.photoIndex = index || 0;
                this.photoOpen = this.photoSlides.length > 0;
            },
            closePhotos() {
                this.photoOpen = false;
                this.photoIndex = 0;
            },
            nextPhoto() {
                if (!this.photoSlides.length) return;
                this.photoIndex = (this.photoIndex + 1) % this.photoSlides.length;
            },
            prevPhoto() {
                if (!this.photoSlides.length) return;
                this.photoIndex = (this.photoIndex - 1 + this.photoSlides.length) % this.photoSlides.length;
            }
         }"
         @keydown.escape.window="if (photoOpen) closePhotos()"
         @keydown.arrow-right.window="if (photoOpen) nextPhoto()"
         @keydown.arrow-left.window="if (photoOpen) prevPhoto()">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-volt">Substation Survey #{{ $survey->id }}</p>
                <h1 class="al-display text-2xl font-extrabold text-theme sm:text-3xl" style="color: var(--al-text)">{{ $survey->substation_name ?: ($survey->substation?->name ?? 'Substation') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $survey->substation_code }} · {{ $survey->substation_type ?: 'Type not captured' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $colors[$survey->status] ?? 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200' }}">
                    {{ $survey->display_status }}
                </span>
                @if($survey->is_locked)
                    <span class="rounded-full bg-seas-950 px-3 py-1.5 text-xs font-bold text-white dark:bg-white/15">Locked</span>
                @endif
                <a href="{{ route('substation-surveys.index') }}" class="al-btn al-btn-primary">Back</a>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">Capacity (MVA)</div>
                <div class="al-display mt-1 text-3xl font-extrabold text-theme" style="color: var(--al-text)">{{ $survey->capacity_mva ?? '—' }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">Transformers</div>
                <div class="al-display mt-1 text-3xl font-extrabold" style="color: #E10600;">{{ $survey->transformer_count ?? '—' }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">Surveyor</div>
                <div class="mt-1 text-lg font-extrabold text-theme">{{ $survey->surveyor?->name ?? '—' }}</div>
                <div class="text-xs text-muted">{{ $survey->surveyed_at?->format('d M Y, H:i') }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">Reviewed</div>
                <div class="mt-1 text-lg font-extrabold text-theme">{{ $survey->reviewed_at?->format('d M Y') ?? '—' }}</div>
                <div class="text-xs text-muted">{{ $survey->reviewer?->name }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="al-panel space-y-2 p-5 text-sm">
                <h3 class="mb-2 font-bold text-theme">Hierarchy &amp; Location</h3>
                <div class="flex justify-between gap-3"><span class="text-muted">Region</span><span class="text-right font-medium text-theme">{{ $survey->region?->name ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Circle</span><span class="text-right font-medium text-theme">{{ $survey->circle?->name ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Division</span><span class="text-right font-medium text-theme">{{ $survey->division?->name ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Zone</span><span class="text-right font-medium text-theme">{{ $survey->zone?->name ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Substation</span><span class="text-right font-medium text-theme">{{ $survey->substation_name ?: '—' }}</span></div>
                <div class="flex justify-between gap-3">
                    <span class="text-muted">GPS</span>
                    <span class="text-right font-medium text-theme">
                        @if($survey->latitude && $survey->longitude)
                            <a href="https://www.openstreetmap.org/?mlat={{ $survey->latitude }}&mlon={{ $survey->longitude }}#map=18/{{ $survey->latitude }}/{{ $survey->longitude }}"
                               target="_blank" rel="noopener" class="text-volt hover:underline">{{ $survey->latitude }}, {{ $survey->longitude }}</a>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="flex justify-between gap-3"><span class="text-muted">GPS Accuracy</span><span class="text-right font-medium text-theme">{{ $survey->gps_accuracy ? $survey->gps_accuracy.' m' : '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Locked</span><span class="text-right font-medium text-theme">{{ $survey->locked_at?->format('d M Y, H:i') ?? '—' }}</span></div>
            </div>
            <div class="al-panel space-y-2 p-5 text-sm">
                <h3 class="mb-2 font-bold text-theme">Substation Asset Details</h3>
                <div class="flex justify-between gap-3"><span class="text-muted">Substation Type</span><span class="text-right font-medium text-theme">{{ $survey->substation_type ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Capacity (MVA)</span><span class="text-right font-medium text-theme">{{ $survey->capacity_mva ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Transformer Count</span><span class="text-right font-medium text-theme">{{ $survey->transformer_count ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Incoming Voltage</span><span class="text-right font-medium text-theme">{{ $survey->incoming_voltage ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Outgoing Voltage</span><span class="text-right font-medium text-theme">{{ $survey->outgoing_voltage ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Declared Feeders</span><span class="text-right font-medium text-theme">{{ $survey->feeder_count_declared ?? '—' }}</span></div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="al-panel space-y-2 p-5 text-sm">
                <h3 class="mb-2 font-bold text-theme">Metering</h3>
                <div class="flex justify-between gap-3"><span class="text-muted">Meter Number</span><span class="text-right font-medium text-theme">{{ $survey->meter_number ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Meter Make</span><span class="text-right font-medium text-theme">{{ $survey->meter_make ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Meter Serial No</span><span class="text-right font-medium text-theme">{{ $survey->meter_serial_no ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Metering Type</span><span class="text-right font-medium text-theme">{{ $survey->metering_type ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">CT Ratio</span><span class="text-right font-medium text-theme">{{ $survey->ct_ratio ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">PT Ratio</span><span class="text-right font-medium text-theme">{{ $survey->pt_ratio ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">MF</span><span class="text-right font-medium text-theme">{{ $survey->mf ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Meter Condition</span><span class="text-right font-medium text-theme">{{ $survey->meter_condition ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Meter Working</span><span class="text-right font-medium text-theme">{{ $survey->meter_working === null ? '—' : ($survey->meter_working ? 'Yes' : 'No') }}</span></div>
            </div>
            <div class="al-panel space-y-2 p-5 text-sm">
                <h3 class="mb-2 font-bold text-theme">Observation &amp; Remarks</h3>
                <div>
                    <div class="text-muted">Observation</div>
                    <p class="mt-1 font-medium text-theme">{{ $survey->observation ?: '—' }}</p>
                </div>
                <div class="pt-2">
                    <div class="text-muted">Remarks</div>
                    <p class="mt-1 font-medium text-theme">{{ $survey->remarks ?: '—' }}</p>
                </div>
                <div class="pt-2">
                    <div class="text-muted">Review Remarks</div>
                    <p class="mt-1 font-medium text-theme">{{ $survey->review_remarks ?: '—' }}</p>
                </div>
            </div>
        </div>

        <div class="al-panel p-5">
            <div class="mb-4">
                <h3 class="al-display font-bold text-theme">Photos</h3>
                <p class="text-xs text-muted">Substation · meter · nameplate · SLD — click a thumbnail to enlarge (images load only on demand).</p>
            </div>

            @if(count($gallery))
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($gallery as $index => $photo)
                        <button type="button"
                                class="group rounded-xl border p-3 text-left transition hover:opacity-90"
                                style="border-color: var(--al-border); background: var(--al-surface-2);"
                                @click="openPhotos({{ \Illuminate\Support\Js::from($gallery) }}, {{ $index }})">
                            <div class="text-sm font-bold text-theme">{{ $photo['label'] }}</div>
                            <div class="mt-1 text-xs font-bold text-volt group-hover:underline">View →</div>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">No photos uploaded yet.</p>
            @endif

            {{-- Photo lightbox: image URL loads only after View is clicked --}}
            <div x-show="photoOpen"
                 x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6"
                 role="dialog"
                 aria-modal="true"
                 aria-label="Substation survey photo">
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

        @if($survey->status === 'rejected' && $survey->review_remarks)
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-200">
                <div class="font-bold">Rejected</div>
                <p class="mt-1 text-sm">{{ $survey->review_remarks }}</p>
            </div>
        @endif

        @if($survey->is_locked)
            <div class="al-panel space-y-3 p-5">
                <h3 class="font-bold text-theme">Unlock for rework</h3>
                <p class="text-sm text-muted">Surveyor edits are blocked while locked. Unlock to allow corrections and re-submit.</p>
                <form method="POST" action="{{ route('substation-surveys.unlock', $survey) }}">
                    @csrf
                    <button type="submit" class="al-btn al-btn-primary">Unlock</button>
                </form>
            </div>
        @endif

        @if(!empty($canApprove) && $canApprove && $survey->status === 'pending_approval')
            <div class="al-panel space-y-4 p-5" style="border-color: rgba(225, 6, 0, 0.28);">
                <h3 class="font-bold text-theme">Manager Review</h3>
                <p class="text-sm text-muted">Review hierarchy, asset data, metering and photos above before approving or rejecting. Approve locks the survey and copies GPS to the substation master.</p>
                <form method="POST" action="{{ route('substation-surveys.approve', $survey) }}" class="space-y-3">
                    @csrf
                    <textarea name="review_remarks" rows="2" class="al-input" placeholder="Optional approval remarks"></textarea>
                    <button type="submit" class="al-btn al-btn-primary">Approve</button>
                </form>
                <form method="POST" action="{{ route('substation-surveys.reject', $survey) }}" class="space-y-3">
                    @csrf
                    <textarea name="review_remarks" rows="2" required class="al-input" placeholder="Rejection reason (required)"></textarea>
                    <button type="submit" class="al-btn al-btn-ink">Reject</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
