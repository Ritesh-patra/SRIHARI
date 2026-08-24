<x-app-layout>
    @php
        $colors = [
            'draft' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
            'sld_pending' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-300',
            'pending_approval' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
            'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
            'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
            'rejected' => 'bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-200',
        ];
        $sldPhotos = $sldPhotos ?? $survey->sldPhotos ?? collect();
        $counts = $reviewCounts ?? $survey->reviewCounts($dtrSurveys ?? collect());

        $gallery = [];
        if ($sldPhotos->isNotEmpty()) {
            foreach ($sldPhotos as $i => $photo) {
                $gallery[] = [
                    'url' => $photo->url ?? asset('storage/'.$photo->path),
                    'label' => ($i === 0 ? 'SLD · Latest' : 'SLD · Previous'),
                    'meta' => $photo->created_at?->format('d M Y, H:i') ?? '',
                    'latest' => $i === 0,
                ];
            }
        } elseif ($survey->sld_photo) {
            $gallery[] = [
                'url' => $survey->sld_photo_url,
                'label' => 'SLD Photo',
                'meta' => '',
                'latest' => true,
            ];
        }
        if ($survey->new_meter_photo) {
            $gallery[] = [
                'url' => \App\Support\SurveyPhotoStorage::url($survey->new_meter_photo),
                'label' => 'New Meter Photo',
                'meta' => '',
                'latest' => false,
            ];
        }
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-5 sm:space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-volt">Feeder Survey #{{ $survey->id }}</p>
                <h1 class="al-display text-2xl font-extrabold text-theme sm:text-3xl" style="color: var(--al-text)">{{ $survey->feeder_name }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $survey->feeder_code }} · {{ $survey->substation_name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $colors[$survey->status] ?? 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200' }}">
                    {{ $survey->display_status }}
                </span>
                @if($survey->is_locked)
                    <span class="rounded-full bg-seas-950 px-3 py-1.5 text-xs font-bold text-white dark:bg-white/15">Locked</span>
                @endif
                <a href="{{ route('feeder-surveys.index') }}" class="al-btn al-btn-primary">Back</a>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="al-panel px-4 py-3" style="border-color: rgba(245, 158, 11, 0.35); background: color-mix(in srgb, #F59E0B 10%, var(--al-surface));">
                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">DTR pending</div>
                <div class="al-display mt-1 text-2xl font-extrabold" style="color: #E10600;">{{ $counts['dtr_pending'] }}</div>
            </div>
            <div class="al-panel px-4 py-3" style="border-color: rgba(249, 115, 22, 0.35); background: color-mix(in srgb, #F97316 10%, var(--al-surface));">
                <div class="text-[11px] font-bold uppercase tracking-wider text-orange-700 dark:text-orange-300">SLD pending</div>
                <div class="al-display mt-1 text-2xl font-extrabold" style="color: #E10600;">{{ $counts['sld_pending'] }}</div>
            </div>
            <div class="al-panel px-4 py-3" style="border-color: rgba(16, 185, 129, 0.35); background: color-mix(in srgb, #10B981 10%, var(--al-surface));">
                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Approved DTRs</div>
                <div class="al-display mt-1 text-2xl font-extrabold text-theme" style="color: var(--al-text)">{{ $counts['dtr_approved'] }}</div>
            </div>
        </div>
        <p class="text-xs text-muted">Locks after SLD submit / approve. Auto-unlocks after 2 days so surveyors can be reassigned. Production cron should run <code class="rounded px-1.5 py-0.5 text-[11px]" style="background: var(--al-surface-2);">php artisan schedule:run</code>.</p>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">DTRs Expected</div>
                <div class="al-display mt-1 text-3xl font-extrabold text-theme" style="color: var(--al-text)">{{ $survey->dtrs_expected }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">DTRs Completed</div>
                <div class="al-display mt-1 text-3xl font-extrabold" style="color: #E10600;">{{ $survey->dtrs_completed }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">Surveyor</div>
                <div class="mt-1 text-lg font-extrabold text-theme">{{ $survey->surveyor?->name ?? '—' }}</div>
                <div class="text-xs text-muted">{{ $survey->surveyed_at?->format('d M Y, H:i') }}</div>
            </div>
            <div class="al-panel p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-muted">Reviewed</div>
                <div class="mt-1 text-lg font-extrabold text-theme">{{ $survey->reviewed_at?->format('d M Y') ?? '—' }}</div>
                <div class="text-xs text-muted">{{ $survey->reviewed_at?->format('H:i') }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="al-panel space-y-2 p-5 text-sm">
                <h3 class="mb-2 font-bold text-theme">Hierarchy</h3>
                <div class="flex justify-between gap-3"><span class="text-muted">Region</span><span class="text-right font-medium text-theme">{{ $survey->region?->name }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Circle</span><span class="text-right font-medium text-theme">{{ $survey->circle?->name }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Division</span><span class="text-right font-medium text-theme">{{ $survey->division?->name }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Zone</span><span class="text-right font-medium text-theme">{{ $survey->zone?->name }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Substation</span><span class="text-right font-medium text-theme">{{ $survey->substation_name }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Feeder</span><span class="text-right font-medium text-theme">{{ $survey->feeder_name }} ({{ $survey->feeder_code }})</span></div>
            </div>
            <div class="al-panel space-y-2 p-5 text-sm">
                <h3 class="mb-2 font-bold text-theme">Field Verification</h3>
                <div class="flex justify-between gap-3"><span class="text-muted">Voltage</span><span class="text-right font-medium text-theme">{{ $survey->feeder_voltage ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Metering</span><span class="text-right font-medium text-theme">{{ $survey->metering_type ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">CTPT</span><span class="text-right font-medium text-theme">{{ $survey->ctpt_available ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">ME CT</span><span class="text-right font-medium text-theme">{{ $survey->me_ct_ratio ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">New MF</span><span class="text-right font-medium text-theme">{{ $survey->new_mf ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Smart Meter</span><span class="text-right font-medium text-theme">{{ $survey->new_smart_meter_installed ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">GPS</span><span class="text-right font-medium text-theme">{{ $survey->latitude }}, {{ $survey->longitude }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Created</span><span class="text-right font-medium text-theme">{{ $survey->created_at?->format('d M Y, H:i') }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Updated</span><span class="text-right font-medium text-theme">{{ $survey->updated_at?->format('d M Y, H:i') }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Locked</span><span class="text-right font-medium text-theme">{{ $survey->locked_at?->format('d M Y, H:i') ?? '—' }}</span></div>
            </div>
        </div>

        <div class="al-panel overflow-hidden p-5"
             x-data="{
                slides: {{ Js::from($gallery) }},
                i: 0,
                open: false,
                next() { if (!this.slides.length) return; this.i = (this.i + 1) % this.slides.length },
                prev() { if (!this.slides.length) return; this.i = (this.i - 1 + this.slides.length) % this.slides.length },
                show(idx) { this.i = idx; this.open = true },
             }"
             @keydown.escape.window="if (open) open = false"
             @keydown.arrow-right.window="if (open) next()"
             @keydown.arrow-left.window="if (open) prev()">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="al-display font-bold text-theme">Photo Gallery</h3>
                    <p class="text-xs text-muted">
                        SLD + meter photos · click to enlarge
                        @if(count($gallery) > 1)
                            · {{ count($gallery) }} images
                        @endif
                        · last {{ \App\Models\FeederSurvey::SLD_PHOTO_RETENTION }} SLD uploads retained
                    </p>
                </div>
                <template x-if="slides.length">
                    <div class="text-xs font-bold text-muted">
                        <span x-text="i + 1"></span> / <span x-text="slides.length"></span>
                    </div>
                </template>
            </div>

            @if(count($gallery))
                <div class="relative overflow-hidden rounded-xl border" style="border-color: var(--al-border); background: var(--al-surface-2);">
                    <button type="button"
                            class="group flex w-full items-center justify-center px-3 py-4 sm:px-10"
                            @click="show(i)"
                            :aria-label="'Enlarge ' + (slides[i]?.label || 'photo')">
                        <img :src="slides[i].url"
                             :alt="slides[i].label"
                             class="mx-auto max-h-[22rem] w-full object-contain transition group-hover:opacity-95 sm:max-h-[26rem]"
                             style="background: transparent;">
                    </button>

                    <template x-if="slides.length > 1">
                        <div>
                            <button type="button"
                                    class="absolute left-2 top-1/2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border text-theme shadow-sm transition hover:opacity-90"
                                    style="background: var(--al-surface); border-color: var(--al-border);"
                                    @click.stop="prev()"
                                    aria-label="Previous photo">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                            </button>
                            <button type="button"
                                    class="absolute right-2 top-1/2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border text-theme shadow-sm transition hover:opacity-90"
                                    style="background: var(--al-surface); border-color: var(--al-border);"
                                    @click.stop="next()"
                                    aria-label="Next photo">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-bold text-theme" x-text="slides[i]?.label"></div>
                        <div class="text-xs text-muted" x-text="slides[i]?.meta || 'Tap image to enlarge'"></div>
                    </div>
                    <template x-if="slides.length > 1">
                        <div class="flex items-center gap-1.5">
                            <template x-for="(s, idx) in slides" :key="idx">
                                <button type="button"
                                        class="h-2.5 rounded-full transition"
                                        :class="idx === i ? 'w-6' : 'w-2.5 opacity-40 hover:opacity-70'"
                                        :style="idx === i ? 'background:#E10600' : 'background: var(--al-muted)'"
                                        @click="i = idx"
                                        :aria-label="'Go to slide ' + (idx + 1)"></button>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Lightbox --}}
                <div x-show="open"
                     x-cloak
                     class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6"
                     role="dialog"
                     aria-modal="true"
                     aria-label="Enlarged photo">
                    <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="open = false"></div>
                    <div class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-white" x-text="slides[i]?.label"></div>
                                <div class="truncate text-xs text-white/60" x-text="slides[i]?.meta"></div>
                            </div>
                            <button type="button"
                                    class="al-btn al-btn-primary shrink-0 px-3 py-2"
                                    @click="open = false"
                                    aria-label="Close lightbox">
                                Close
                            </button>
                        </div>
                        <div class="relative flex min-h-0 flex-1 items-center justify-center rounded-xl bg-black/40 p-2 sm:p-4">
                            <img :src="slides[i].url"
                                 :alt="slides[i].label"
                                 class="max-h-[78vh] w-auto max-w-full object-contain">
                            <template x-if="slides.length > 1">
                                <div>
                                    <button type="button"
                                            class="absolute left-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25"
                                            @click.stop="prev()"
                                            aria-label="Previous photo">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                                    </button>
                                    <button type="button"
                                            class="absolute right-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25"
                                            @click.stop="next()"
                                            aria-label="Next photo">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-sm text-muted">No photos uploaded yet.</p>
            @endif
        </div>

        <div class="al-panel overflow-hidden">
            <div class="border-b px-5 py-4" style="border-color: var(--al-border)">
                <h3 class="font-bold text-theme">DTR Surveys by this surveyor</h3>
                <p class="text-xs text-muted">Progress on DTRs under this feeder</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-theme">
                    <thead class="text-left text-xs font-bold uppercase tracking-wider text-muted" style="background: var(--al-surface-2)">
                        <tr>
                            <th class="px-4 py-3">DTR</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dtrSurveys as $dtr)
                            <tr class="border-t" style="border-color: var(--al-border)">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-theme">{{ $dtr->dtr_name }}</div>
                                    <div class="text-xs text-muted">{{ $dtr->dtr_code }}</div>
                                </td>
                                <td class="px-4 py-3 text-theme">{{ str_replace('_', ' ', $dtr->status) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $dtr->surveyed_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-muted">No DTR surveys yet for this feeder.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                <p class="text-sm text-muted">Surveyor edits are blocked while locked. Unlock to allow reassignment or SLD re-upload.</p>
                <form method="POST" action="{{ route('feeder-surveys.unlock', $survey) }}">
                    @csrf
                    <button type="submit" class="al-btn al-btn-primary">Unlock</button>
                </form>
            </div>
        @endif

        @if(!empty($canApprove) && $canApprove && $survey->status === 'pending_approval')
            <div class="al-panel space-y-4 p-5" style="border-color: rgba(225, 6, 0, 0.28);">
                <h3 class="font-bold text-theme">Manager Review</h3>
                <p class="text-sm text-muted">Review hierarchy, field data, SLD images, and DTR progress above before approving or rejecting. Approve locks the survey.</p>
                <form method="POST" action="{{ route('feeder-surveys.approve', $survey) }}" class="space-y-3">
                    @csrf
                    <textarea name="review_remarks" rows="2" class="al-input" placeholder="Optional approval remarks"></textarea>
                    <button type="submit" class="al-btn al-btn-primary">Approve</button>
                </form>
                <form method="POST" action="{{ route('feeder-surveys.reject', $survey) }}" class="space-y-3">
                    @csrf
                    <textarea name="review_remarks" rows="2" required class="al-input" placeholder="Rejection reason (required)"></textarea>
                    <button type="submit" class="al-btn al-btn-ink">Reject</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
