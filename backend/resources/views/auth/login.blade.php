<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-600">Login ID / Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="seas-input" placeholder="surveyor@seas.test" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-sm font-semibold text-slate-600">Password</label>
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-seas-600 hover:text-seas-800" href="{{ route('password.request') }}">Forgot?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="seas-input" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-seas-600 focus:ring-seas-500" name="remember">
            <span class="text-sm text-slate-600">Remember me</span>
        </label>

        <button type="submit" class="seas-btn-primary w-full py-3.5 text-base">
            LOGIN
        </button>

        <p class="text-center text-xs text-slate-400">Super Admin web: super@seas.test · password</p>
    </form>
</x-guest-layout>
