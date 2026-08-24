<x-guest-layout>
    <div class="space-y-5 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-seas-950 text-volt">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 18h.01M8 21h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z"/></svg>
        </div>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-seas-900">Use SEAS Mobile App</h2>
            <p class="mt-2 text-sm text-seas-500">
                This web portal is for <strong>Admin</strong> only.
                Field Executives, Managers, and Project Managers sign in through the Flutter app.
            </p>
        </div>
        <div class="rounded-2xl bg-seas-50 px-4 py-3 text-left text-xs text-seas-600 space-y-1">
            <div><strong class="text-seas-900">Web:</strong> Admin · Users · Masters · Reports</div>
            <div><strong class="text-seas-900">Flutter:</strong> DTR Survey · Consumer Survey · Approvals · Pipeline</div>
        </div>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="seas-btn-primary w-full">Logout & try Admin login</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="seas-btn-primary inline-flex w-full">Admin Login</a>
        @endauth
    </div>
</x-guest-layout>
