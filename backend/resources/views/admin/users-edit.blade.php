<x-app-layout>
    @php
        $roleMeta = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'project_manager' => 'Project Manager',
            'manager' => 'Manager',
            'field_executive' => 'Field Executive',
        ];
        $canScope = in_array($user->role, ['admin', 'project_manager', 'manager'], true);
        $scopeType = $user->isProjectManager() ? 'circle' : ($user->isManager() ? 'division' : 'region');
        $scopeOptions = $user->isProjectManager() ? $circles : ($user->isManager() ? $divisions : $regions);
        $selectedScopes = $user->scopes->where('scope_type', $scopeType)->pluck('scope_id')->all();
    @endphp

    <div class="mx-auto w-full space-y-5 sm:space-y-6" x-data="{ role: '{{ old('role', $user->role) }}' }">
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
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
                        <h1 class="al-display mt-3 text-3xl font-bold text-white sm:text-4xl">Edit User</h1>
                        <p class="mt-2 text-sm text-white/75">{{ $user->name }}</p>
                    </div>
                    <a href="{{ route('admin.users') }}" class="al-btn al-btn-glass self-start px-4 py-2.5 text-sm">← Back</a>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('admin.users') }}" class="al-btn-light rounded-full px-4 py-1.5 text-xs font-bold">Users</a>
                    <a href="{{ route('admin.users', ['tab' => 'roles']) }}" class="rounded-full bg-white/10 px-4 py-1.5 text-xs font-bold text-white ring-1 ring-white/25">Roles</a>
                </div>
            </div>
        </section>

        <section class="al-panel overflow-hidden">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5 p-5 sm:p-6">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Full Name *</label>
                        <input name="name" required class="al-input" value="{{ old('name', $user->name) }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Email *</label>
                        <input name="email" type="email" required class="al-input" value="{{ old('email', $user->email) }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Password (leave blank to keep)</label>
                        <input name="password" type="password" class="al-input" placeholder="New password · min 6 chars" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Role *</label>
                        <select name="role" required class="al-input" x-model="role">
                            @foreach(\App\Models\User::ROLES as $role)
                                @if($role === 'super_admin' && !auth()->user()->isSuperAdmin()) @continue @endif
                                <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $roleMeta[$role] ?? $role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="role === 'field_executive'" x-cloak>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Manager</label>
                        <select name="supervisor_id" class="al-input">
                            <option value="">No manager</option>
                            @foreach($managers as $m)
                                <option value="{{ $m->id }}" @selected(old('supervisor_id', $user->supervisor_id) == $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex w-full items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold text-theme" style="border-color: var(--al-border); background: var(--al-surface-2)">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-seas-300 text-volt focus:ring-volt">
                            Active
                        </label>
                    </div>
                    <div class="md:col-span-2 rounded-2xl border p-4" style="border-color: var(--al-border); background: var(--al-surface-2)" x-show="role === 'manager' || role === 'project_manager'" x-cloak>
                        <div class="mb-2 text-[11px] font-bold uppercase tracking-wide text-volt">Feature permissions (Super Admin control)</div>
                        <label class="flex w-full items-start gap-2 text-sm font-semibold text-theme">
                            <input type="checkbox" name="can_consumer_survey_approve" value="1" @checked(old('can_consumer_survey_approve', $user->can_consumer_survey_approve)) class="mt-0.5 rounded border-seas-300 text-volt focus:ring-volt">
                            <span>
                                Consumer Survey Approval (Manager app)
                                <span class="mt-0.5 block text-[11px] font-normal text-muted">
                                    Managers and Project Managers can always open Consumer Approval (approve / reject / delete).
                                    This checkbox is kept for status display; role already grants access.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                @if($canScope)
                    <div class="rounded-2xl border p-4" style="border-color: var(--al-border); background: var(--al-surface-2)">
                        <div class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted">Geographic scope ({{ ucfirst($scopeType) }})</div>
                        <input type="hidden" name="scope_type" value="{{ $scopeType }}">
                        <select name="scope_ids[]" multiple class="al-input h-32 text-sm">
                            @foreach($scopeOptions as $opt)
                                <option value="{{ $opt->id }}" @selected(in_array($opt->id, old('scope_ids', $selectedScopes), true))>{{ $opt->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-[11px] text-muted">Hold Ctrl / Cmd to select multiple.</p>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-end gap-2 border-t pt-4" style="border-color: var(--al-border)">
                    <a href="{{ route('admin.users') }}" class="al-btn al-btn-ghost">Cancel</a>
                    <button class="al-btn al-btn-ink">Update User</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
