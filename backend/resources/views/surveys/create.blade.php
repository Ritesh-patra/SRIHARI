<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="seas-eyebrow">Field capture</div>
            <h1 class="font-display text-xl font-bold text-seas-900 sm:text-2xl">New DTR Survey</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1100px] animate-fade-up">
        <div class="mb-6 rounded-2xl border border-seas-100 bg-seas-50 px-5 py-4 text-sm text-seas-700">
            GPS auto-captures on open. Old meter fields show only when Smart Meter = <strong class="text-volt">Not Installed</strong>.
        </div>

        @if($errors->any())
            <div class="mb-4 seas-alert-error">
                <ul class="list-disc ms-4 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('surveys._form')
    </div>
</x-app-layout>
