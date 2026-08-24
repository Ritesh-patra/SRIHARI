<x-app-layout>
    @php
        $roleMeta = [
            'super_admin' => ['label' => 'Super Admin', 'tone' => 'bg-seas-950 text-white dark:bg-white dark:text-seas-950'],
            'admin' => ['label' => 'Admin', 'tone' => 'bg-volt text-white'],
            'project_manager' => ['label' => 'Project Manager', 'tone' => 'bg-seas-800 text-white dark:bg-zinc-700'],
            'manager' => ['label' => 'Manager', 'tone' => 'bg-volt-soft text-volt-deep dark:bg-volt/20 dark:text-red-300'],
            'field_executive' => ['label' => 'Field Executive', 'tone' => 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200'],
        ];
        $byRole = $users->groupBy('role');
    @endphp

    <div class="mx-auto w-full space-y-5 sm:space-y-6" x-data="{ tab: @js(request('tab', 'users')), q: '', role: '' }">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="al-hero relative p-6 sm:p-7">
            <div class="relative z-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            People
                        </span>
                        <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Users</h1>
                        <p class="mt-2 text-sm text-white/75">Manage all system users across panels.</p>
                    </div>
                    <button type="button" @click="tab='create'" class="al-btn al-btn-light self-start px-4 py-2.5 text-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        Add User
                    </button>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="button" @click="tab='users'" class="rounded-full px-4 py-1.5 text-xs font-bold transition"
                            :class="tab==='users' ? 'bg-white text-seas-950' : 'bg-white/10 text-white ring-1 ring-white/25'">Users</button>
                    <button type="button" @click="tab='roles'" class="rounded-full px-4 py-1.5 text-xs font-bold transition"
                            :class="tab==='roles' ? 'bg-white text-seas-950' : 'bg-white/10 text-white ring-1 ring-white/25'">Roles</button>
                    <button type="button" @click="tab='create'" class="rounded-full px-4 py-1.5 text-xs font-bold transition"
                            :class="tab==='create' ? 'bg-white text-seas-950' : 'bg-white/10 text-white ring-1 ring-white/25'">Create</button>
                </div>
            </div>
        </section>

        <section x-show="tab==='roles'" x-cloak class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
            @foreach($roleMeta as $key => $meta)
                <div class="al-panel p-4">
                    <span class="al-chip {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
                    <div class="al-display mt-3 text-3xl font-bold text-theme">{{ $byRole->get($key)?->count() ?? 0 }}</div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-muted">Users</div>
                </div>
            @endforeach
        </section>

        <section x-show="tab==='create'" x-cloak class="al-panel overflow-hidden">
            <div class="border-b px-5 py-4" style="border-color: var(--al-border)">
                <div class="al-display text-lg font-bold text-theme">Create user</div>
                <div class="text-xs text-muted">Name, email, password, role, manager, status</div>
                <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    Mobile app login: create users here on <strong>production</strong> (mrhari.co.in), not only on local admin.
                    App roles: <strong>Field Executive</strong>, Manager, Project Manager, or Super Admin.
                    Plain <strong>Admin</strong> is web portal only.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Full name *</label>
                    <input name="name" required class="al-input" placeholder="e.g. Anuj Sharma" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Email *</label>
                    <input name="email" type="email" required class="al-input" placeholder="name@seas.test" value="{{ old('email') }}">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Password *</label>
                    <input name="password" type="password" required class="al-input" placeholder="Min 6 characters">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Role *</label>
                    <select name="role" required class="al-input">
                        @foreach(\App\Models\User::ROLES as $role)
                            @if($role === 'super_admin' && !auth()->user()->isSuperAdmin()) @continue @endif
                            <option value="{{ $role }}" @selected(old('role', 'field_executive') === $role)>{{ $roleMeta[$role]['label'] ?? $role }}{{ in_array($role, ['field_executive','manager','project_manager','super_admin'], true) ? ' · app' : ' · web only' }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-muted">Default: Field Executive (mobile app). Admin cannot log into the APK.</p>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Manager (Field Executive)</label>
                    <select name="supervisor_id" class="al-input">
                        <option value="">Optional</option>
                        @foreach($managers as $m)
                            <option value="{{ $m->id }}" @selected(old('supervisor_id') == $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex w-full items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold text-theme" style="border-color: var(--al-border); background: var(--al-surface-2)">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-seas-300 text-volt focus:ring-volt">
                        Active account
                    </label>
                </div>
                <div class="md:col-span-2 xl:col-span-3">
                    <button class="al-btn al-btn-primary">Create user</button>
                </div>
            </form>
        </section>

        <div x-show="tab==='users'">
            <section class="al-panel mb-5 p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative min-w-0 flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                        <input type="search" x-model="q" placeholder="Search name, email…" class="al-input !pl-10">
                    </div>
                    <select x-model="role" class="al-input lg:max-w-[14rem]">
                        <option value="">All Roles</option>
                        @foreach($roleMeta as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="al-btn al-btn-ink shrink-0">Filter</button>
                </div>
            </section>

            <section class="al-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="al-table min-w-full">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Manager</th>
                                <th>Feature permission</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                @php $meta = $roleMeta[$user->role] ?? ['label' => $user->roleLabel(), 'tone' => 'bg-seas-100 text-seas-700']; @endphp
                                <tr x-show="(!q || '{{ strtolower($user->name.' '.$user->email) }}'.includes(q.toLowerCase())) && (!role || role === '{{ $user->role }}')">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-seas-950 text-xs font-bold text-white dark:bg-volt">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                            <div class="font-semibold text-theme">{{ $user->name }}</div>
                                        </div>
                                    </td>
                                    <td class="text-muted">{{ $user->email }}</td>
                                    <td><span class="al-chip {{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                                    <td class="text-muted">{{ $user->supervisor?->name ?? '—' }}</td>
                                    <td>
                                        @if(in_array($user->role, ['manager', 'project_manager', 'supervisor'], true))
                                            <span class="al-chip {{ $user->canApproveConsumerSurveys() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                                                Consumer Approve: {{ $user->canApproveConsumerSurveys() ? 'ON' : 'OFF' }}
                                            </span>
                                        @elseif(in_array($user->role, ['super_admin', 'admin'], true))
                                            <span class="al-chip bg-seas-100 text-seas-700">Full access</span>
                                        @else
                                            <span class="text-xs text-muted">Field capture</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="al-chip {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-seas-100 text-seas-500 dark:bg-white/5 dark:text-zinc-400' }}">
                                            {{ $user->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-bold text-blue-600 hover:underline dark:text-sky-400">Edit / Permissions</a>
                                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button class="text-xs font-bold text-volt hover:underline" @if($user->id === auth()->id()) disabled @endif>Toggle</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete {{ $user->name }}? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs font-bold text-volt hover:underline" @if($user->id === auth()->id()) disabled @endif>Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
