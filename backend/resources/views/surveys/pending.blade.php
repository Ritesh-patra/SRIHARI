<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pending Approvals</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">DTR</th>
                                <th class="px-4 py-3">Surveyor</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($surveys as $survey)
                                <tr>
                                    <td class="px-4 py-3">#{{ $survey->id }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $survey->dtr_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $survey->dtr_code }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $survey->surveyor?->name }}</td>
                                    <td class="px-4 py-3">{{ $survey->updated_at?->format('d M Y, H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('surveys.show', $survey) }}" class="text-blue-600 font-semibold hover:underline">Review</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-400">No pending surveys.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $surveys->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
