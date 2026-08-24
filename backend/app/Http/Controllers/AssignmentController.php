<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\User;
use App\Models\WorkAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        WorkAssignment::syncClosedStatuses();

        $assignments = WorkAssignment::with(['assignee', 'assigner', 'feeder', 'dtr'])
            ->when($user->isFieldExecutive(), fn ($q) => $q->where('assigned_to', $user->id))
            ->when($user->isManager() || $user->isProjectManager(), fn ($q) => $q->where(function ($q) use ($user) {
                $q->where('assigned_by', $user->id)
                    ->orWhereIn('assigned_to', User::where('supervisor_id', $user->id)->pluck('id'));
            }))
            ->latest()
            ->paginate(20);

        $fieldExecs = User::where('role', User::ROLE_FIELD_EXECUTIVE)->where('is_active', true)->orderBy('name')->get();
        $feeders = Feeder::orderBy('name')->get();
        $dtrs = Dtr::orderBy('name')->get();

        return view('assignments.index', compact('assignments', 'fieldExecs', 'feeders', 'dtrs'));
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->canApproveSurveys() || Auth::user()->isAdmin(), 403);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'feeder_id' => ['nullable', 'exists:feeders,id'],
            'dtr_id' => ['nullable', 'exists:dtrs,id'],
            'work_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['feeder_id']) && empty($data['dtr_id'])) {
            return back()->withErrors(['dtr_id' => 'Select a feeder or DTR.']);
        }

        WorkAssignment::syncClosedStatuses();

        $status = WorkAssignment::STATUS_OPEN;
        if (\Illuminate\Support\Carbon::parse($data['work_date'])->lt(now()->startOfDay())) {
            $status = WorkAssignment::STATUS_CLOSED;
        }

        $assignment = WorkAssignment::create([
            ...$data,
            'assigned_by' => Auth::id(),
            'status' => $status,
        ]);

        AppNotification::notifyUser(
            (int) $assignment->assigned_to,
            'New work assignment',
            ($assignment->notes ?: 'You have been assigned field work.')
                .' Work date: '.\Illuminate\Support\Carbon::parse($data['work_date'])->format('d M Y'),
            route('assignments.index'),
            WorkAssignment::class,
            (int) $assignment->id
        );

        ActivityLog::record('assignment.created', $assignment);

        return back()->with('success', 'Work assigned.');
    }

    public function updateStatus(Request $request, WorkAssignment $assignment)
    {
        $user = Auth::user();
        abort_unless(
            $user->isAdmin() || (int) $assignment->assigned_to === (int) $user->id || (int) $assignment->assigned_by === (int) $user->id,
            403
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'done', 'closed'])],
        ]);

        $assignment->update($data);

        return back()->with('success', 'Assignment status updated.');
    }
}
