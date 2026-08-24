<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="seas-eyebrow">Field operations</p>
                <h2 class="seas-title mt-1 text-2xl lg:text-3xl">DTR Surveys</h2>
                <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</p>
            </div>
            @if(auth()->user()->isSurveyor() || auth()->user()->isAdmin())
                <a href="{{ route('surveys.create') }}" class="seas-btn-primary">+ New DTR Survey</a>
            @endif
        </div>
    </x-slot>

    <div class="seas-page">
        @if(session('success'))
            <div class="seas-alert-success">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="seas-stat"><div class="num text-slate-700">{{ $stats['draft'] }}</div><div class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Draft</div></div>
            <div class="seas-stat"><div class="num text-amber-500">{{ $stats['pending'] }}</div><div class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Pending</div></div>
            <div class="seas-stat"><div class="num text-emerald-600">{{ $stats['approved'] }}</div><div class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Approved</div></div>
            <div class="seas-stat"><div class="num text-rose-500">{{ $stats['rejected'] }}</div><div class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Rejected</div></div>
        </div>

        <div class="seas-table-wrap">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>DTR</th>
                            <th>Surveyor</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($surveys as $survey)
                            <tr>
                                <td class="px-4 py-3.5 font-semibold text-slate-500">#{{ $survey->id }}</td>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-seas-900">{{ $survey->dtr_name }}</div>
                                    <div class="text-xs text-slate-400">{{ $survey->dtr_code }}</div>
                                </td>
                                <td class="px-4 py-3.5">{{ $survey->surveyor?->name }}</td>
                                <td class="px-4 py-3.5">{{ $survey->surveyed_at?->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-3.5">
                                    @php
                                        $colors = [
                                            'draft' => 'bg-slate-100 text-slate-700',
                                            'pending_approval' => 'bg-amber-100 text-amber-800',
                                            'approved' => 'bg-emerald-100 text-emerald-800',
                                            'rejected' => 'bg-rose-100 text-rose-800',
                                        ];
                                    @endphp
                                    <span class="seas-badge {{ $colors[$survey->status] ?? 'bg-slate-100' }}">
                                        {{ str_replace('_', ' ', ucfirst($survey->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <a href="{{ route('surveys.show', $survey) }}" class="font-bold text-seas-600 hover:text-seas-800">View →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-400">No surveys yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $surveys->links() }}</div>
        </div>
    </div>
</x-app-layout>
