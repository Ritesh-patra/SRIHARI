<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="seas-eyebrow">Bulk load</div>
                <h1 class="font-display text-xl font-extrabold text-seas-900 sm:text-2xl">CSV Import</h1>
            </div>
            <a href="{{ route('admin.masters') }}" class="seas-btn-secondary !py-2 text-xs">← Masters</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6 animate-fade-up">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif
        @if(session('import_errors'))
            <div class="seas-alert-error">
                <div class="font-bold mb-2">Row errors</div>
                <ul class="list-disc ms-4 text-sm">
                    @foreach(session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="seas-card overflow-hidden">
            <div class="border-b border-seas-100 bg-seas-950 px-5 py-4 text-white">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-volt">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M12 3v12m0 0 4-4m-4 4-4-4"/></svg>
                    </span>
                    <div>
                        <div class="font-display font-extrabold">Upload master CSV</div>
                        <div class="text-[11px] text-white/45">Feeders · DTRs · Consumers — poles not importable</div>
                    </div>
                </div>
            </div>
            <div class="space-y-4 p-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-canvas-soft p-3 text-[11px] text-seas-600">
                        <div class="font-extrabold text-seas-900">feeders</div>
                        code, name, substation
                    </div>
                    <div class="rounded-xl bg-canvas-soft p-3 text-[11px] text-seas-600">
                        <div class="font-extrabold text-seas-900">dtrs</div>
                        code, name, capacity_kva, feeder_code
                    </div>
                    <div class="rounded-xl bg-canvas-soft p-3 text-[11px] text-seas-600">
                        <div class="font-extrabold text-seas-900">consumers</div>
                        name, phone, ivrs, msn, dtr_code
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.masters.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-seas-400">Type</label>
                        <select name="type" required class="seas-input">
                            <option value="feeders">Feeders</option>
                            <option value="dtrs">DTRs</option>
                            <option value="consumers">Consumers</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-seas-400">CSV file</label>
                        <input type="file" name="file" accept=".csv,text/csv" required class="seas-input file:mr-3 file:rounded-lg file:border-0 file:bg-seas-950 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                    </div>
                    <button class="seas-btn-primary w-full">Import now</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
