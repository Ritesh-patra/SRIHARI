@php
    $tone = match ($status) {
        \App\Models\ReadingUpload::STATUS_COMPLETED => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        \App\Models\ReadingUpload::STATUS_PROCESSING => 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        \App\Models\ReadingUpload::STATUS_FAILED => 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
        default => 'bg-zinc-500/15 text-zinc-600 dark:text-zinc-300',
    };
@endphp
<span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-bold {{ $tone }}">
    {{ ucfirst((string) $status) }}
</span>
