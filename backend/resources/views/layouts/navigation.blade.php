<nav x-data="{ open: false }" class="seas-nav">
    <div class="seas-container">
        <div class="flex h-[4.25rem] items-center justify-between gap-4">
            <div class="flex items-center gap-8 min-w-0">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 shrink-0 group">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-seas-hero text-white shadow-glow group-hover:scale-105 transition">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h7l-1 8 10-14h-7l1-6Z"/></svg>
                    </span>
                    <span class="leading-tight">
                        <span class="block font-display text-lg font-bold tracking-tight text-seas-900">SEAS</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Energy Audit</span>
                    </span>
                </a>

                <div class="hidden lg:flex items-center gap-1">
                    @php
                        $links = [
                            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard'],
                            ['route' => 'surveys.index', 'match' => 'surveys.*', 'label' => 'DTR Survey'],
                            ['route' => 'consumer.index', 'match' => 'consumer.*', 'label' => 'Consumer Survey'],
                            ['route' => 'dtr.status', 'match' => 'dtr.status', 'label' => 'DTR Status'],
                        ];
                    @endphp
                    @foreach($links as $link)
                        <a href="{{ route($link['route']) }}"
                           class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs($link['match']) ? 'bg-seas-900 text-white shadow-sm' : 'text-slate-600 hover:bg-seas-50 hover:text-seas-800' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                    @if(auth()->user()->isSupervisor() || auth()->user()->isAdmin())
                        <a href="{{ route('surveys.pending') }}"
                           class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs('surveys.pending') ? 'bg-amber-500 text-white' : 'text-slate-600 hover:bg-amber-50 hover:text-amber-700' }}">
                            Approvals
                        </a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.users') }}"
                           class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs('admin.*') ? 'bg-seas-900 text-white' : 'text-slate-600 hover:bg-seas-50' }}">
                            Admin
                        </a>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex items-center gap-3">
                <div class="text-right leading-tight">
                    <div class="text-sm font-bold text-seas-900">{{ Auth::user()->name }}</div>
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-volt-deep">{{ Auth::user()->role }}</div>
                </div>
                <div class="relative" x-data="{ openUser: false }">
                    <button @click="openUser = !openUser" class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-volt to-seas-500 text-white font-display font-bold shadow-sm">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </button>
                    <div x-show="openUser" @click.outside="openUser = false" x-cloak
                         class="absolute right-0 mt-2 w-48 rounded-2xl border border-slate-100 bg-white p-2 shadow-seas-lg z-50">
                        <a href="{{ route('profile.edit') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-seas-50">Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Log Out</button>
                        </form>
                    </div>
                </div>
            </div>

            <button @click="open = ! open" class="lg:hidden inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-seas-800">
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open}" class="inline" stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open}" class="hidden" stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden border-t border-slate-100 bg-white/95">
        <div class="seas-container py-3 space-y-1">
            <a href="{{ route('dashboard') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-seas-900 text-white' : 'text-slate-700 hover:bg-seas-50' }}">Dashboard</a>
            <a href="{{ route('surveys.index') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('surveys.*') ? 'bg-seas-900 text-white' : 'text-slate-700 hover:bg-seas-50' }}">DTR Survey</a>
            <a href="{{ route('consumer.index') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('consumer.*') ? 'bg-seas-900 text-white' : 'text-slate-700 hover:bg-seas-50' }}">Consumer Survey</a>
            <a href="{{ route('dtr.status') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('dtr.status') ? 'bg-seas-900 text-white' : 'text-slate-700 hover:bg-seas-50' }}">DTR Status</a>
            @if(auth()->user()->isSupervisor() || auth()->user()->isAdmin())
                <a href="{{ route('surveys.pending') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-50">Approvals</a>
            @endif
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.users') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-seas-50">Admin</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="pt-2">
                @csrf
                <button class="w-full rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-rose-600 hover:bg-rose-50">Log Out</button>
            </form>
        </div>
    </div>
</nav>
