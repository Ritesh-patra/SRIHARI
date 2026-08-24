@php
    $columns = $sample->isNotEmpty() ? array_keys((array) $sample->first()) : [];
@endphp
<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif

        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10">
                <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">{{ $labels[$upload->type] ?? $upload->type }}</span>
                <h1 class="al-display mt-3 text-2xl font-bold text-white sm:text-3xl">{{ $upload->original_name }}</h1>
                <p class="mt-2 text-sm text-white/75">
                    Uploaded {{ $upload->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                    @if($upload->size_bytes) · {{ number_format($upload->size_bytes / 1048576, 1) }} MB @endif
                </p>
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted">Status</div>
                    <div class="mt-1">@include('reading-uploads._status-badge', ['status' => $upload->status])</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted">Rows</div>
                    <div class="mt-1 text-sm font-semibold text-theme">
                        {{ number_format((int) $upload->rows_imported) }} imported / {{ number_format((int) $upload->rows_total) }} read
                    </div>
                    @if((int) $upload->rows_failed > 0)
                        <div class="text-[11px] text-rose-600 dark:text-rose-400">{{ number_format((int) $upload->rows_failed) }} skipped (no key column)</div>
                    @endif
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted">Period</div>
                    <div class="mt-1 text-sm font-semibold text-theme">
                        @if($upload->period_label)
                            {{ $upload->period_label }}
                        @elseif($upload->period_from || $upload->period_to)
                            {{ $upload->period_from?->format('d M Y') ?? '…' }} – {{ $upload->period_to?->format('d M Y') ?? '…' }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted">Processed</div>
                    <div class="mt-1 text-sm font-semibold text-theme">
                        {{ $upload->processed_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? '—' }}
                    </div>
                </div>
            </div>

            @if($upload->error)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                    {{ $upload->error }}
                </div>
            @endif

            @if(is_array($upload->headers_json) && count($upload->headers_json))
                <div class="mt-4">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted">File headers</div>
                    <div class="mt-1 text-[11px] text-muted">{{ implode(' · ', $upload->headers_json) }}</div>
                </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t pt-5" style="border-color: var(--al-border)">
                <a href="{{ route('reading-uploads.index', ['type' => $upload->type]) }}" class="al-btn al-btn-ghost !py-2 !px-3 text-xs">Back to Reading Upload</a>
                @if($upload->status === \App\Models\ReadingUpload::STATUS_FAILED)
                    <form method="POST" action="{{ route('reading-uploads.reprocess', $upload) }}">
                        @csrf
                        <button type="submit" class="al-btn al-btn-primary !py-2 !px-3 text-xs">Retry import</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="al-panel overflow-hidden">
            <div class="border-b px-5 py-4 al-display font-bold text-theme" style="border-color: var(--al-border)">
                First {{ $sample->count() }} parsed row{{ $sample->count() === 1 ? '' : 's' }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-muted" style="background: var(--al-surface-2)">
                            @foreach($columns as $column)
                                <th class="px-4 py-2.5 whitespace-nowrap">{{ str_replace('_', ' ', $column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--al-border)">
                        @forelse($sample as $row)
                            <tr style="border-color: var(--al-border)">
                                @foreach($columns as $column)
                                    <td class="px-4 py-2.5 max-w-[18rem] truncate text-theme" title="{{ (string) ($row->{$column} ?? '') }}">
                                        {{ \Illuminate\Support\Str::limit((string) ($row->{$column} ?? ''), 60) }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-10 text-center text-muted">No parsed rows yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
