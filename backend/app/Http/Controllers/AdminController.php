<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Circle;
use App\Models\Consumer;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\Pole;
use App\Models\Region;
use App\Models\Substation;
use App\Models\User;
use App\Models\UserScope;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::with(['supervisor', 'scopes'])->orderBy('role')->orderBy('name')->get();
        $managers = User::whereIn('role', ['manager', 'project_manager'])->orderBy('name')->get();
        $circles = Circle::orderBy('name')->get();
        $divisions = Division::orderBy('name')->get();
        $regions = Region::orderBy('name')->get();

        return view('admin.users', compact('users', 'managers', 'circles', 'divisions', 'regions'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(User::ROLES)],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['role'] !== User::ROLE_FIELD_EXECUTIVE) {
            $data['supervisor_id'] = null;
        }

        if ($data['role'] === User::ROLE_SUPER_ADMIN && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can create Super Admin.');
        }

        // Plain password — User model casts password as 'hashed' (do not Hash::make here).
        $isManagerRole = in_array($data['role'], [User::ROLE_MANAGER, User::ROLE_PROJECT_MANAGER, 'supervisor'], true);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            // Role grants access; keep flag ON for manager/PM so Users UI stays consistent.
            'can_consumer_survey_approve' => $isManagerRole,
            'email_verified_at' => now(),
        ]);

        ActivityLog::record('user.created', $user);

        $mobileHint = $user->isMobileUser()
            ? ' This role can sign in to the SEAS mobile app.'
            : ($user->role === User::ROLE_ADMIN
                ? ' Admin is web-only — use Field Executive / Manager / Project Manager for the mobile app.'
                : '');

        return back()->with('success', 'User created successfully.'.$mobileHint);
    }

    public function editUser(User $user)
    {
        $managers = User::whereIn('role', ['manager', 'project_manager'])->orderBy('name')->get();
        $circles = Circle::orderBy('name')->get();
        $divisions = Division::orderBy('name')->get();
        $regions = Region::orderBy('name')->get();
        $user->load('scopes');

        return view('admin.users-edit', compact('user', 'managers', 'circles', 'divisions', 'regions'));
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(User::ROLES)],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
            'can_consumer_survey_approve' => ['nullable', 'boolean'],
            'scope_type' => ['nullable', Rule::in(['region', 'circle', 'division'])],
            'scope_ids' => ['nullable', 'array'],
            'scope_ids.*' => ['integer'],
        ]);

        if ($data['role'] === User::ROLE_SUPER_ADMIN && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can assign Super Admin role.');
        }

        if ($user->isSuperAdmin() && $data['role'] !== User::ROLE_SUPER_ADMIN) {
            $otherSupers = User::where('role', User::ROLE_SUPER_ADMIN)->where('id', '!=', $user->id)->count();
            if ($otherSupers < 1) {
                return back()->withErrors(['role' => 'At least one Super Admin is required.']);
            }
        }

        if ($data['role'] !== User::ROLE_FIELD_EXECUTIVE) {
            $data['supervisor_id'] = null;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->supervisor_id = $data['supervisor_id'] ?? null;
        $user->is_active = $request->boolean('is_active');
        // Role grants consumer approval; keep flag ON for manager/PM (UI status chip).
        $user->can_consumer_survey_approve = in_array(
            $data['role'],
            [User::ROLE_MANAGER, User::ROLE_PROJECT_MANAGER, 'supervisor'],
            true
        );

        if (! empty($data['password'])) {
            // Plain password — hashed via User model cast.
            $user->password = $data['password'];
            $user->force_password_change = false;
        }

        $user->save();

        if ($request->filled('scope_type') && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PROJECT_MANAGER, User::ROLE_MANAGER], true)) {
            UserScope::where('user_id', $user->id)->where('scope_type', $data['scope_type'])->delete();
            foreach ($data['scope_ids'] ?? [] as $scopeId) {
                UserScope::create([
                    'user_id' => $user->id,
                    'scope_type' => $data['scope_type'],
                    'scope_id' => $scopeId,
                ]);
            }
        }

        ActivityLog::record('user.updated', $user, [
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function toggleUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        if ($user->isSuperAdmin() && $user->is_active) {
            $otherActiveSupers = User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();
            if ($otherActiveSupers < 1) {
                return back()->withErrors(['user' => 'At least one active Super Admin is required.']);
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();
        ActivityLog::record('user.toggled', $user, ['is_active' => $user->is_active]);

        return back()->with('success', $user->name.' is now '.($user->is_active ? 'active' : 'disabled').'.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->isSuperAdmin()) {
            $otherSupers = User::where('role', User::ROLE_SUPER_ADMIN)->where('id', '!=', $user->id)->count();
            if ($otherSupers < 1) {
                return back()->withErrors(['user' => 'At least one Super Admin is required.']);
            }
        }

        $name = $user->name;
        $deletedId = $user->id;
        UserScope::where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        $user->delete();
        ActivityLog::record('user.deleted', null, ['name' => $name, 'id' => $deletedId]);

        return back()->with('success', $name.' deleted successfully.');
    }

    public function updateAssignment(Request $request, User $user)
    {
        $data = $request->validate([
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($user->isFieldExecutive()) {
            $user->supervisor_id = $data['supervisor_id'] ?: null;
        }

        $user->is_active = $request->boolean('is_active');
        $user->save();
        ActivityLog::record('user.updated', $user);

        return back()->with('success', 'User assignment updated.');
    }

    public function updateScopes(Request $request, User $user)
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in(['region', 'circle', 'division'])],
            'scope_ids' => ['nullable', 'array'],
            'scope_ids.*' => ['integer'],
        ]);

        UserScope::where('user_id', $user->id)->where('scope_type', $data['scope_type'])->delete();

        foreach ($data['scope_ids'] ?? [] as $scopeId) {
            UserScope::create([
                'user_id' => $user->id,
                'scope_type' => $data['scope_type'],
                'scope_id' => $scopeId,
            ]);
        }

        ActivityLog::record('user.scopes_updated', $user, $data);

        return back()->with('success', 'Geographic scope updated for '.$user->name);
    }

    public function hierarchy()
    {
        $stats = [
            'regions' => Region::count(),
            'circles' => Circle::count(),
            'divisions' => Division::count(),
            'zones' => Zone::count(),
            'substations' => Substation::count(),
            'feeders' => Feeder::count(),
            'dtrs' => Dtr::count(),
            'consumers' => Consumer::count(),
        ];

        // Compact summary only — never nest 9k feeders / 160k DTRs into HTML.
        $regions = Region::query()
            ->withCount('circles')
            ->orderBy('name')
            ->get(['id', 'name']);

        $zones = Zone::query()
            ->with(['division:id,name,circle_id', 'division.circle:id,name,region_id', 'division.circle.region:id,name'])
            ->withCount('substations')
            ->orderBy('name')
            ->paginate(40);

        return view('admin.hierarchy', compact('regions', 'zones', 'stats'));
    }

    public function poles()
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin(), 403);

        $poles = Pole::with(['dtr.feeder'])
            ->withCount(['consumers', 'consumerSurveys'])
            ->latest()
            ->paginate(40);

        return view('admin.poles', compact('poles'));
    }

    public function destroyPole(Pole $pole)
    {
        $user = auth()->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $label = $pole->pole_no;
        $deletedId = $pole->id;

        $surveys = \App\Models\ConsumerSurvey::query()->where('pole_id', $pole->id)->get();
        foreach ($surveys as $row) {
            \App\Support\ConsumerSurveyDeleter::delete($row, $user);
        }

        Pole::where('previous_pole_id', $pole->id)->update(['previous_pole_id' => null, 'source_type' => 'dtr']);
        $pole->delete();
        ActivityLog::record('pole.deleted', null, ['pole_no' => $label, 'id' => $deletedId, 'by' => $user->id]);

        return back()->with('success', "Pole {$label} deleted.");
    }

    public function activity()
    {
        $logs = ActivityLog::with('user')->latest()->paginate(40);

        return view('admin.activity', compact('logs'));
    }

    public function settings()
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin(), 403);

        return view('admin.settings', [
            'counts' => [
                'users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'regions' => Region::count(),
            ],
        ]);
    }
}
