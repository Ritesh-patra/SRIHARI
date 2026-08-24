<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        <section class="al-hero relative p-6 sm:p-7 lg:p-8">
            <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">System</span>
                    <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Settings</h1>
                    <p class="mt-2 max-w-md text-sm text-white/75">Users, masters, and role authority for the energy audit CRM.</p>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
                        <div class="al-display text-2xl font-bold text-white">{{ $counts['users'] }}</div>
                        <div class="text-[10px] font-bold uppercase text-white/55">Users</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
                        <div class="al-display text-2xl font-bold text-white">{{ $counts['active_users'] }}</div>
                        <div class="text-[10px] font-bold uppercase text-white/55">Active</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
                        <div class="al-display text-2xl font-bold text-white">{{ $counts['regions'] }}</div>
                        <div class="text-[10px] font-bold uppercase text-white/55">Regions</div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="mb-3">
                <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">Authority</div>
                <h3 class="al-display text-lg font-bold text-theme">Role matrix</h3>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach([
                    ['Super Admin', 'Full system · users · masters · settings', 'bg-seas-950', 'SA'],
                    ['Admin', 'Users · master CRUD · reports · export', 'bg-volt', 'AD'],
                    ['Project Manager', 'Circle scope · approve · circle reports', 'bg-seas-800', 'PM'],
                    ['Manager', 'Division scope · approve · assign FE work', 'bg-seas-700', 'MG'],
                    ['Field Executive', 'DTR + consumer surveys · poles · photos', 'bg-seas-600', 'FE'],
                ] as [$title, $desc, $bg, $abbr])
                    <div class="al-panel p-5">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $bg }} al-display text-xs font-bold text-white">{{ $abbr }}</span>
                            <div>
                                <div class="al-display font-bold text-theme">{{ $title }}</div>
                                <div class="text-xs text-muted">{{ $desc }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="al-panel p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-volt/15 text-volt">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M12 3a9 9 0 1 0 0 18"/></svg>
                </span>
                <div class="text-sm">
                    <div class="al-display font-bold text-theme">Data rules</div>
                    <p class="mt-1 text-muted">Master = Region → DTR + Consumers. <strong class="text-theme">Poles are field-only</strong> (added in Consumer Survey). Survey snapshots do not overwrite master data.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.users') }}" class="al-btn al-btn-primary !py-2 text-xs">Users & Roles</a>
                        <a href="{{ route('admin.masters') }}" class="al-btn al-btn-ghost !py-2 text-xs">Master Data</a>
                        <a href="{{ route('admin.activity') }}" class="al-btn al-btn-ghost !py-2 text-xs">Activity</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
