<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="seas-eyebrow">DTR Survey</p>
            <h2 class="seas-title mt-1 text-2xl lg:text-3xl">Edit Survey #{{ $survey->id }}</h2>
        </div>
    </x-slot>

    <div class="seas-page" style="max-width: 56rem;">
        @if($survey->status === 'rejected' && $survey->review_remarks)
            <div class="seas-alert-error"><strong>Rejected:</strong> {{ $survey->review_remarks }}</div>
        @endif
        @if($errors->any())
            <div class="seas-alert-error">
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
